<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\FeeStatus;
use App\Enums\Gender;
use App\Enums\ParentLinkStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\PersonType;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Enums\Role;
use App\Enums\RoverAttendanceStatus;
use App\Enums\ScoutSection;
use App\Enums\ShopItemStatus;
use App\Enums\StudentStatus;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\AnnualFee;
use App\Models\AnnualFeeYear;
use App\Models\AttendanceRecord;
use App\Models\ClassFee;
use App\Models\Group;
use App\Models\ParentStudentLink;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\RoverAttendanceRecord;
use App\Models\ShopItem;
use App\Models\Student;
use App\Models\User;
use App\Support\Import\SpreadsheetReader;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Imports the legacy Attendance and Finance workbooks. Sheets run in
 * dependency order; rows upsert by natural key; per-row errors are
 * collected, never fatal. A dry run rolls everything back.
 */
class LegacyImportService
{
    /**
     * Sheet => required normalised columns, in import order.
     */
    public const SHEETS = [
        'Students' => ['name', 'nationalid'],
        'Users' => ['name', 'nationalid'],
        'ParentLinks' => ['parent', 'student'],
        'Groups' => ['id', 'name'],
        'GroupMembers' => ['groupid', 'student'],
        'GroupLeaders' => ['groupid', 'user'],
        'GroupAssistantLeaders' => ['groupid', 'student'],
        'Activities' => ['id', 'name', 'date'],
        'Attendance' => ['activityid', 'student', 'status'],
        'RoverAttendance' => ['activityid', 'student', 'status'],
        'ClassFeeConfig' => ['activityid', 'amount'],
        'ClassFees' => ['activityid', 'student', 'amount'],
        'Configuration' => ['key', 'value'],
        'UserPermissions' => ['user', 'permission'],
        'AnnualFeeConfig' => ['year', 'amount'],
        'AnnualFees' => ['year', 'amount'],
        'Payments' => ['id', 'type', 'amount'],
        'ShopItems' => ['id', 'name', 'price'],
        'Purchases' => ['id', 'student', 'item', 'quantity'],
    ];

    /**
     * Column aliases: canonical => accepted normalised headers.
     */
    private const ALIASES = [
        'id' => ['id', 'legacyid', 'key', 'uid'],
        'nationalid' => ['nationalid', 'nid', 'idcard', 'idcardno', 'idnumber'],
        'student' => ['student', 'studentid', 'studentnationalid', 'scoutid', 'scout'],
        'parent' => ['parent', 'parentid', 'parentnationalid', 'parentuserid'],
        'user' => ['user', 'userid', 'usernationalid', 'leader', 'leaderid', 'leadernationalid'],
        'groupid' => ['groupid', 'group'],
        'activityid' => ['activityid', 'activity'],
        'item' => ['item', 'itemid', 'shopitemid', 'shopitem'],
        'indexnumber' => ['indexnumber', 'index', 'indexno'],
        'pinsalt' => ['pinsalt', 'salt'],
        'pinhash' => ['pinhash', 'hash'],
        'role' => ['role', 'roles'],
        'dateofbirth' => ['dateofbirth', 'dob', 'birthdate'],
        'primarymobile' => ['primarymobile', 'mobile', 'phone'],
        'stock' => ['stock', 'stockqty', 'quantityinstock'],
        'feeamount' => ['feeamount', 'fee'],
        'chargefee' => ['chargefee', 'charged'],
        'allstudents' => ['allstudents', 'all'],
        'payableid' => ['payableid', 'feeid', 'purchaseid', 'referenceid'],
        'persontype' => ['persontype', 'type'],
    ];

    /** @var array<string, array<string, int>> legacy key => new id, per entity */
    private array $map = [];

    /** @var list<array{sheet: string, row: int, message: string}> */
    private array $errors = [];

    /** @var array<string, array{imported: int, errors: int}> */
    private array $counts = [];

    private ?User $actor = null;

    private int $currentRow = 0;

    public function __construct(
        private AuditLogService $audit,
        private PaymentBalanceService $balances,
        private SettingsService $settings,
    ) {}

    /**
     * @return array{sheets: list<array{name: string, rows: int, valid: int, invalid: int, missing: list<string>, known: bool}>, missing_identity: list<string>}
     */
    public function inspect(string $path): array
    {
        $sheets = SpreadsheetReader::read($path);
        $result = [];

        foreach ($sheets as $name => $sheet) {
            $canonical = $this->canonicalSheet($name);
            $required = $canonical ? self::SHEETS[$canonical] : [];
            $missing = array_values(array_filter($required, fn ($column) => $this->headerFor($sheet['headers'], $column) === null));
            $valid = 0;

            foreach ($sheet['rows'] as $row) {
                $ok = $missing === [];

                foreach ($required as $column) {
                    $ok = $ok && filled($this->value($row, $column));
                }

                $valid += $ok ? 1 : 0;
            }

            $result[] = [
                'name' => $name,
                'rows' => count($sheet['rows']),
                'valid' => $valid,
                'invalid' => count($sheet['rows']) - $valid,
                'missing' => $missing,
                'known' => $canonical !== null,
            ];
        }

        $present = array_filter(array_map(fn ($s) => $this->canonicalSheet($s['name']), $result));
        $missingIdentity = array_values(array_diff(['Students', 'Users'], $present));

        return ['sheets' => $result, 'missing_identity' => $missingIdentity];
    }

    /**
     * @return array{dry_run: bool, counts: array<string, array{imported: int, errors: int}>, errors: list<array{sheet: string, row: int, message: string}>}
     */
    public function import(string $path, bool $dryRun, ?User $actor = null, ?string $onlySheet = null): array
    {
        $this->map = [];
        $this->errors = [];
        $this->counts = [];
        $this->actor = $actor;

        $sheets = [];

        foreach (SpreadsheetReader::read($path) as $name => $sheet) {
            $canonical = $this->canonicalSheet($name);

            if ($canonical !== null) {
                $sheets[$canonical] = $sheet;
            }
        }

        DB::beginTransaction();

        try {
            foreach (array_keys(self::SHEETS) as $sheetName) {
                if (! isset($sheets[$sheetName]) || ($onlySheet !== null && strcasecmp($onlySheet, $sheetName) !== 0)) {
                    continue;
                }

                $this->importSheet($sheetName, $sheets[$sheetName]['rows']);
            }

            $this->recalculateImportedBalances();

            if ($dryRun) {
                DB::rollBack();
            } else {
                $this->audit->record('import.legacy', null, ['counts' => $this->counts, 'errors' => count($this->errors)], $actor);
                DB::commit();
                $this->settings->flush();
            }
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return ['dry_run' => $dryRun, 'counts' => $this->counts, 'errors' => $this->errors];
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function importSheet(string $sheet, array $rows): void
    {
        $this->counts[$sheet] = ['imported' => 0, 'errors' => 0];
        $method = 'import'.$sheet;

        foreach ($rows as $index => $row) {
            $this->currentRow = $index + 2;

            try {
                DB::transaction(fn () => $this->{$method}($row));
                $this->counts[$sheet]['imported']++;
            } catch (Throwable $e) {
                $this->counts[$sheet]['errors']++;
                $this->errors[] = ['sheet' => $sheet, 'row' => $index + 2, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importStudents(array $row): void
    {
        $nationalId = strtoupper($this->required($row, 'nationalid'));
        $student = Student::withTrashed()->firstOrNew(['national_id' => $nationalId]);

        $student->fill([
            'index_number' => $this->value($row, 'indexnumber') ?: ($student->index_number ?? 'LEG-'.$nationalId),
            'name' => $this->required($row, 'name'),
            'email' => $this->value($row, 'email') ?: $student->email,
            'gender' => Gender::fromLoose($this->value($row, 'gender'))?->value,
            'section' => $this->section($this->value($row, 'section'))->value,
            'status' => $this->studentStatus($this->value($row, 'status'))->value,
            'date_of_birth' => SpreadsheetReader::parseDate($this->value($row, 'dateofbirth'))?->toDateString(),
            'parent_name' => $this->value($row, 'parentname'),
            'primary_mobile' => $this->value($row, 'primarymobile'),
            'secondary_mobile' => $this->value($row, 'secondarymobile'),
            'permanent_address' => $this->value($row, 'permanentaddress'),
            'present_address' => $this->value($row, 'presentaddress'),
            'class_name' => $this->value($row, 'classname'),
            'patrol' => $this->value($row, 'patrol'),
            'legacy_id' => $this->value($row, 'id') ?: $student->legacy_id,
        ]);

        if ($student->status === StudentStatus::Active && $student->verified_at === null) {
            $student->verified_at = now();
        }

        $student->save();
        $this->remember('student', [$student->legacy_id, $nationalId], $student->id);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importUsers(array $row): void
    {
        $nationalId = strtoupper($this->required($row, 'nationalid'));
        $roles = $this->roles($this->value($row, 'role'));
        $user = User::withTrashed()->firstOrNew(['national_id' => $nationalId]);
        $status = UserStatus::fromLoose($this->value($row, 'status')) ?? UserStatus::Active;

        $user->fill([
            'name' => $this->required($row, 'name'),
            'email' => $this->value($row, 'email') ?: $user->email,
            'legacy_id' => $this->value($row, 'id') ?: $user->legacy_id,
        ]);

        $pin = $this->value($row, 'pin');
        $salt = $this->value($row, 'pinsalt');
        $hash = $this->value($row, 'pinhash') ?: (preg_match('/^[a-f0-9]{64}$/i', (string) $pin) ? $pin : null);

        if ($hash !== null) {
            $user->forceFill(['legacy_pin_hash' => strtolower($hash), 'legacy_pin_salt' => $salt ?: null, 'password' => Str::random(40)]);
        } elseif (filled($pin)) {
            $user->forceFill(['password' => (string) $pin, 'legacy_pin_hash' => null, 'legacy_pin_salt' => null]);
        } elseif (! $user->exists) {
            // No PIN in the sheet: never a shared default. The account stays inactive until an admin resets the PIN.
            $user->forceFill(['password' => Str::random(40)]);
            $status = UserStatus::Inactive;
            $this->errors[] = ['sheet' => 'Users', 'row' => $this->currentRow, 'message' => "{$nationalId}: no PIN in the sheet, so the account was imported inactive. Reset the PIN to activate it."];
        }

        $user->status = $status;
        $user->verified_at ??= now();

        $studentId = $this->lookup('student', $this->value($row, 'student'), false) ?? $this->lookup('student', $nationalId, false);
        $user->student_id = $studentId && ! User::query()->where('student_id', $studentId)->whereKeyNot($user->id ?? 0)->exists() ? $studentId : $user->student_id;
        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        if ($user->student_id && ! in_array(Role::Student, $roles, true)) {
            $roles[] = Role::Student;
        }

        $user->syncRoles($roles);
        $this->remember('user', [$user->legacy_id, $nationalId], $user->id);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importParentLinks(array $row): void
    {
        $parentId = $this->lookup('user', $this->required($row, 'parent'));
        $studentId = $this->lookup('student', $this->required($row, 'student'));
        $status = ParentLinkStatus::fromLoose($this->value($row, 'status')) ?? ParentLinkStatus::Approved;

        if ($status->isOpen() && ParentStudentLink::query()->where('student_id', $studentId)->where('parent_user_id', '!=', $parentId)
            ->whereIn('status', [ParentLinkStatus::Pending->value, ParentLinkStatus::Approved->value])->exists()) {
            throw new RuntimeException('This scout already has another pending or approved parent.');
        }

        ParentStudentLink::query()->updateOrCreate(['parent_user_id' => $parentId, 'student_id' => $studentId], ['status' => $status]);
        User::query()->findOrFail($parentId)->assignRole(Role::Parent);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importGroups(array $row): void
    {
        $legacyId = $this->required($row, 'id');
        $group = Group::withTrashed()->updateOrCreate(['legacy_id' => $legacyId], [
            'name' => $this->required($row, 'name'),
            'type' => $this->value($row, 'type'),
            'status' => RecordStatus::fromLoose($this->value($row, 'status')) ?? RecordStatus::Active,
        ]);

        $this->remember('group', [$legacyId], $group->id);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importGroupMembers(array $row): void
    {
        $groupId = $this->lookup('group', $this->required($row, 'groupid'));
        DB::table('group_members')->insertOrIgnore(['group_id' => $groupId, 'student_id' => $this->lookup('student', $this->required($row, 'student')), 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importGroupLeaders(array $row): void
    {
        $groupId = $this->lookup('group', $this->required($row, 'groupid'));
        $user = User::query()->findOrFail($this->lookup('user', $this->required($row, 'user')));

        if (! $user->hasAnyRole([Role::Leader, Role::Admin])) {
            throw new RuntimeException("{$user->name} does not have the leader role.");
        }

        DB::table('group_leaders')->insertOrIgnore(['group_id' => $groupId, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importGroupAssistantLeaders(array $row): void
    {
        $groupId = $this->lookup('group', $this->required($row, 'groupid'));
        $student = Student::query()->findOrFail($this->lookup('student', $this->required($row, 'student')));

        if ($student->section !== ScoutSection::Rover) {
            throw new RuntimeException('Assistant leaders must be Rover scouts.');
        }

        DB::table('group_assistant_leaders')->insertOrIgnore(['group_id' => $groupId, 'student_id' => $student->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importActivities(array $row): void
    {
        $legacyId = $this->required($row, 'id');
        $date = SpreadsheetReader::parseDate($this->required($row, 'date')) ?? throw new RuntimeException('The date could not be read.');
        $fee = $this->value($row, 'feeamount');

        $activity = Activity::withTrashed()->updateOrCreate(['legacy_id' => $legacyId], [
            'name' => $this->required($row, 'name'),
            'date' => $date->toDateString(),
            'details' => $this->value($row, 'details'),
            'all_students' => SpreadsheetReader::truthy($this->value($row, 'allstudents')),
            'charge_fee' => SpreadsheetReader::truthy($this->value($row, 'chargefee')) || Money::isPositive($fee),
            'fee_amount' => Money::isPositive($fee) ? Money::normalize($fee) : null,
        ]);

        $sections = array_filter(array_map(fn ($s) => ScoutSection::fromLoose($s), $this->list($this->value($row, 'sections'))));
        $activity->syncSections(array_values($sections));

        $groupIds = array_filter(array_map(fn ($g) => $this->lookup('group', $g, false), $this->list($this->value($row, 'groups'))));
        $activity->groups()->sync(array_values(array_unique($groupIds)));

        $this->remember('activity', [$legacyId], $activity->id);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importAttendance(array $row): void
    {
        $status = AttendanceStatus::fromLoose($this->required($row, 'status')) ?? throw new RuntimeException('Unknown attendance status.');

        AttendanceRecord::query()->updateOrCreate(
            ['activity_id' => $this->lookup('activity', $this->required($row, 'activityid')), 'student_id' => $this->lookup('student', $this->required($row, 'student'))],
            ['status' => $status, 'remarks' => $this->value($row, 'remarks'), 'marked_at' => SpreadsheetReader::parseDate($this->value($row, 'markedat'), true) ?? now(), 'marked_by' => $this->actor?->id],
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRoverAttendance(array $row): void
    {
        $status = RoverAttendanceStatus::fromLoose($this->required($row, 'status')) ?? throw new RuntimeException('Unknown Rover attendance status.');
        $required = $this->value($row, 'isrequired');

        RoverAttendanceRecord::query()->updateOrCreate(
            ['activity_id' => $this->lookup('activity', $this->required($row, 'activityid')), 'student_id' => $this->lookup('student', $this->required($row, 'student'))],
            ['status' => $status, 'is_required' => $required === null || SpreadsheetReader::truthy($required), 'marked_at' => now(), 'marked_by' => $this->actor?->id],
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importClassFeeConfig(array $row): void
    {
        Activity::query()->whereKey($this->lookup('activity', $this->required($row, 'activityid')))->update([
            'charge_fee' => true,
            'fee_amount' => Money::normalize($this->required($row, 'amount')),
        ]);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importClassFees(array $row): void
    {
        $activityId = $this->lookup('activity', $this->required($row, 'activityid'));
        $studentId = $this->lookup('student', $this->required($row, 'student'));
        $activity = Activity::withTrashed()->findOrFail($activityId);
        $status = FeeStatus::fromLoose($this->value($row, 'status')) ?? FeeStatus::Pending;

        $fee = ClassFee::query()->updateOrCreate(['activity_id' => $activityId, 'student_id' => $studentId], [
            'amount' => Money::normalize($this->required($row, 'amount')),
            'status' => $status === FeeStatus::Void ? FeeStatus::Void : FeeStatus::Pending,
            'due_date' => SpreadsheetReader::parseDate($this->value($row, 'duedate'))?->toDateString() ?? $activity->date->copy()->addDays(14)->toDateString(),
            'voided_at' => $status === FeeStatus::Void ? now() : null,
            'legacy_id' => $this->value($row, 'id'),
        ]);

        $this->balances->calculateClassFeeBalance($fee);
        $this->remember('class_fee', [$fee->legacy_id], $fee->id);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importConfiguration(array $row): void
    {
        $key = Str::snake(trim($this->required($row, 'key')));
        $allowed = ['default_class_fee', 'shop_enabled', 'proof_max_kb', 'bank_name', 'account_name', 'account_number', 'payment_instructions', 'footer_text'];

        if (! in_array($key, $allowed, true)) {
            throw new RuntimeException("The setting {$key} is not imported.");
        }

        $this->settings->set($key, $this->value($row, 'value'), $this->actor);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importUserPermissions(array $row): void
    {
        $permission = Permission::fromLoose($this->required($row, 'permission')) ?? throw new RuntimeException('Unknown permission.');
        $user = User::query()->findOrFail($this->lookup('user', $this->required($row, 'user')));
        $user->permissionRows()->firstOrCreate(['permission' => $permission->value]);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importAnnualFeeConfig(array $row): void
    {
        $year = (int) $this->required($row, 'year');

        if ($year < 2000) {
            throw new RuntimeException('The year must be 2000 or later.');
        }

        AnnualFeeYear::query()->updateOrCreate(['year' => $year], [
            'amount' => Money::normalize($this->required($row, 'amount')),
            'status' => RecordStatus::fromLoose($this->value($row, 'status')) ?? RecordStatus::Active,
            'created_by' => $this->actor?->id,
        ]);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importAnnualFees(array $row): void
    {
        $year = AnnualFeeYear::query()->where('year', (int) $this->required($row, 'year'))->first()
            ?? throw new RuntimeException('Add this year to AnnualFeeConfig first.');

        $type = PersonType::fromLoose($this->value($row, 'persontype')) ?? (filled($this->value($row, 'user')) ? PersonType::Leader : PersonType::Student);
        $column = $type === PersonType::Leader ? 'user_id' : 'student_id';
        $personId = $type === PersonType::Leader
            ? $this->lookup('user', $this->required($row, 'user'))
            : $this->lookup('student', $this->required($row, 'student'));

        $fee = AnnualFee::query()->updateOrCreate(['annual_fee_year_id' => $year->id, $column => $personId], [
            'person_type' => $type,
            'section' => $type === PersonType::Student ? (ScoutSection::fromLoose($this->value($row, 'section')) ?? Student::withTrashed()->find($personId)?->section)?->value : null,
            'amount' => Money::normalize($this->required($row, 'amount')),
            'status' => FeeStatus::Pending,
            'legacy_id' => $this->value($row, 'id'),
        ]);

        $this->balances->calculateAnnualFeeBalance($fee);
        $this->remember('annual_fee', [$fee->legacy_id], $fee->id);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importPayments(array $row): void
    {
        $legacyId = $this->required($row, 'id');
        $typeText = strtolower(preg_replace('/[^a-z]/i', '', $this->required($row, 'type')));
        $type = match (true) {
            str_contains($typeText, 'class') => 'class_fee',
            str_contains($typeText, 'annual') => 'annual_fee',
            str_contains($typeText, 'shop'), str_contains($typeText, 'purchase') => 'purchase',
            default => throw new RuntimeException('Unknown payment type.'),
        };

        $payableId = $this->lookup($type, $this->required($row, 'payableid'));
        $payable = PaymentService::TYPES[$type]::query()->findOrFail($payableId);
        $status = PaymentStatus::fromLoose($this->value($row, 'status'))
            ?? (in_array(strtolower((string) $this->value($row, 'status')), ['approved', 'verified'], true) ? PaymentStatus::Paid : PaymentStatus::AwaitingVerification);

        Payment::query()->updateOrCreate(['legacy_id' => $legacyId], [
            'payable_type' => $type,
            'payable_id' => $payableId,
            'student_id' => $payable->payableStudentId(),
            'amount' => Money::normalize($this->required($row, 'amount')),
            'method' => PaymentMethod::fromLoose($this->value($row, 'method')) ?? PaymentMethod::Cash,
            'status' => $status,
            'submitted_at' => SpreadsheetReader::parseDate($this->value($row, 'submittedat'), true) ?? now(),
            'verified_at' => SpreadsheetReader::parseDate($this->value($row, 'verifiedat'), true),
            'rejection_reason' => $status === PaymentStatus::Rejected ? ($this->value($row, 'rejectionreason') ?: 'Rejected in the legacy workbook.') : null,
        ]);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importShopItems(array $row): void
    {
        $legacyId = $this->required($row, 'id');
        $stock = (int) ($this->value($row, 'stock') ?? 0);

        $item = ShopItem::withTrashed()->updateOrCreate(['legacy_id' => $legacyId], [
            'name' => $this->required($row, 'name'),
            'description' => $this->value($row, 'description'),
            'price' => Money::normalize($this->required($row, 'price')),
            'stock_qty' => max(0, $stock),
            'status' => ShopItemStatus::fromLoose($this->value($row, 'status')) ?? ShopItemStatus::Active,
        ]);

        $this->remember('shop_item', [$legacyId], $item->id);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importPurchases(array $row): void
    {
        $legacyId = $this->required($row, 'id');
        $item = ShopItem::withTrashed()->findOrFail($this->lookup('shop_item', $this->required($row, 'item')));
        $quantity = max(1, (int) $this->required($row, 'quantity'));
        $unit = Money::normalize($this->value($row, 'unitprice') ?? $item->price);
        $total = Money::normalize($this->value($row, 'total') ?? Money::mul($unit, $quantity));
        $purchaseStatus = PurchaseStatus::fromLoose($this->value($row, 'purchasestatus')) ?? PurchaseStatus::PendingPayment;

        $purchase = Purchase::query()->updateOrCreate(['legacy_id' => $legacyId], [
            'student_id' => $this->lookup('student', $this->required($row, 'student')),
            'total_amount' => $total,
            'outstanding_amount' => $total,
            'payment_status' => FeeStatus::Pending,
            'purchase_status' => $purchaseStatus,
            // Legacy stock figures already reflect paid orders.
            'stock_decremented' => $purchaseStatus->isPaidLifecycle(),
        ]);

        $purchase->items()->delete();
        PurchaseItem::query()->create([
            'purchase_id' => $purchase->id,
            'shop_item_id' => $item->id,
            'item_name_snapshot' => $item->name,
            'quantity' => $quantity,
            'unit_price' => $unit,
            'total_amount' => $total,
        ]);

        $this->remember('purchase', [$legacyId], $purchase->id);
    }

    private function recalculateImportedBalances(): void
    {
        foreach (['class_fee', 'annual_fee', 'purchase'] as $type) {
            foreach (array_unique($this->map[$type] ?? []) as $id) {
                $payable = PaymentService::TYPES[$type]::query()->find($id);

                if ($payable instanceof Purchase && $payable->purchase_status === PurchaseStatus::Cancelled) {
                    continue;
                }

                if ($payable) {
                    $this->balances->recalculate($payable);
                }
            }
        }
    }

    /**
     * Unknown or missing role values are errors, never a silent leader.
     *
     * @return list<Role>
     */
    private function roles(?string $value): array
    {
        $roles = [];

        foreach ($this->list($value) as $item) {
            $role = Role::fromLoose($item) ?? match (strtolower($item)) {
                'scout', 'cub', 'rover' => Role::Student,
                'guardian' => Role::Parent,
                default => null,
            };

            if ($role === null) {
                throw new RuntimeException("Unknown role “{$item}”. The user was not imported.");
            }

            $roles[] = $role;
        }

        if ($roles === []) {
            throw new RuntimeException('No role given. The user was not imported.');
        }

        return array_values(array_unique($roles, SORT_REGULAR));
    }

    private function section(?string $value): ScoutSection
    {
        return ScoutSection::fromLoose($value) ?? match (strtolower(trim((string) $value))) {
            'cub', 'cubs' => ScoutSection::CubScout,
            'precub', 'pre-cub', 'pre cubs' => ScoutSection::PreCub,
            'rovers' => ScoutSection::Rover,
            'scouts' => ScoutSection::Scout,
            default => throw new RuntimeException('Unknown section “'.$value.'”.'),
        };
    }

    private function studentStatus(?string $value): StudentStatus
    {
        return StudentStatus::fromLoose($value) ?? match (strtolower(trim((string) $value))) {
            'verified', 'approved', '' => StudentStatus::Active,
            'left', 'removed', 'archived' => StudentStatus::Inactive,
            default => StudentStatus::Pending,
        };
    }

    /**
     * @return list<string>
     */
    private function list(?string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string) $value) ?: [])));
    }

    /**
     * @param  list<int|string|null>  $keys
     */
    private function remember(string $entity, array $keys, int $id): void
    {
        foreach ($keys as $key) {
            if ($key !== null && $key !== '') {
                $this->map[$entity][strtoupper((string) $key)] = $id;
            }
        }
    }

    private function lookup(string $entity, ?string $key, bool $required = true): ?int
    {
        $key = strtoupper(trim((string) $key));

        if ($key === '') {
            return $required ? throw new RuntimeException("Missing {$entity} reference.") : null;
        }

        $id = $this->map[$entity][$key] ?? match ($entity) {
            'student' => Student::withTrashed()->where('national_id', $key)->orWhere('legacy_id', $key)->value('id'),
            'user' => User::withTrashed()->where('national_id', $key)->orWhere('legacy_id', $key)->value('id'),
            'group' => Group::withTrashed()->where('legacy_id', $key)->value('id'),
            'activity' => Activity::withTrashed()->where('legacy_id', $key)->value('id'),
            'class_fee' => ClassFee::query()->where('legacy_id', $key)->value('id'),
            'annual_fee' => AnnualFee::query()->where('legacy_id', $key)->value('id'),
            'shop_item' => ShopItem::withTrashed()->where('legacy_id', $key)->value('id'),
            'purchase' => Purchase::query()->where('legacy_id', $key)->value('id'),
            default => null,
        };

        if ($id === null && $required) {
            throw new RuntimeException('Unknown '.str_replace('_', ' ', $entity)." “{$key}”.");
        }

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function value(array $row, string $column): ?string
    {
        foreach (self::ALIASES[$column] ?? [$column] as $alias) {
            if (isset($row[$alias]) && $row[$alias] !== '') {
                return $row[$alias];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function required(array $row, string $column): string
    {
        return $this->value($row, $column) ?? throw new RuntimeException("Missing {$column}.");
    }

    /**
     * @param  list<string>  $headers
     */
    private function headerFor(array $headers, string $column): ?string
    {
        foreach (self::ALIASES[$column] ?? [$column] as $alias) {
            if (in_array($alias, $headers, true)) {
                return $alias;
            }
        }

        return null;
    }

    private function canonicalSheet(string $name): ?string
    {
        $normalized = SpreadsheetReader::normalizeHeader($name);

        foreach (array_keys(self::SHEETS) as $sheet) {
            if (strtolower($sheet) === $normalized) {
                return $sheet;
            }
        }

        return null;
    }
}
