<?php

namespace Tests\Feature\Import;

use App\Enums\FeeStatus;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\AnnualFee;
use App\Models\AttendanceRecord;
use App\Models\ClassFee;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ShopItem;
use App\Models\Student;
use App\Models\User;
use App\Services\LegacyImportService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A small demo workbook in the legacy layout.
     */
    public static function demoWorkbook(): string
    {
        $sheets = [
            'Students' => [
                ['ID', 'Index Number', 'Name', 'National ID', 'Email', 'Gender', 'Section', 'Status', 'Date of Birth'],
                ['S1', 'IX001', 'Ali Legacy', 'A1111111', 'ali@example.com', 'male', 'Scouts', 'Active', '01.02.2012'],
                ['S2', 'IX002', 'Aisha Legacy', 'A2222222', 'aisha@example.com', 'F', 'cub', 'active', '2014-05-06'],
                ['S3', 'IX003', 'Rover Legacy', 'A3333333', 'rover@example.com', 'male', 'Rover', 'active', '41000'],
            ],
            'Users' => [
                ['ID', 'Name', 'National ID', 'Email', 'Role', 'Status', 'PIN', 'PIN Salt'],
                ['U1', 'Leader Legacy', 'A9000001', 'leader@example.com', 'leader', 'active', hash('sha256', 'salt1234'), 'salt'],
                ['U2', 'Parent Legacy', 'A9000002', 'parent@example.com', 'parent', 'active', '4321', ''],
                ['U3', 'No Pin', 'A9000003', 'nopin@example.com', 'parent', 'active', '', ''],
                ['U4', 'Mystery Role', 'A9000004', 'mystery@example.com', 'wizard', 'active', '1111', ''],
                ['U5', 'Ali Legacy', 'A1111111', 'ali@example.com', 'student', 'active', '2222', ''],
            ],
            'ParentLinks' => [['Parent ID', 'Student ID', 'Status'], ['U2', 'S1', 'approved']],
            'Groups' => [['ID', 'Name', 'Type'], ['G1', 'Eagle Patrol', 'Patrol']],
            'GroupMembers' => [['Group ID', 'Student ID'], ['G1', 'S1'], ['G1', 'A2222222']],
            'GroupLeaders' => [['Group ID', 'User ID'], ['G1', 'U1']],
            'GroupAssistantLeaders' => [['Group ID', 'Student ID'], ['G1', 'S3'], ['G1', 'S2']],
            'Activities' => [
                ['ID', 'Name', 'Date', 'All Students', 'Sections', 'Groups', 'Charge Fee', 'Fee Amount'],
                ['A1', 'Camp Night', '12.03.2026 18:30', 'no', 'Scout; Cub Scout', 'G1', 'yes', '20'],
            ],
            'Attendance' => [['Activity ID', 'Student ID', 'Status', 'Remarks'], ['A1', 'S1', 'present', ''], ['A1', 'S2', 'Excused', 'Sick']],
            'ClassFees' => [['ID', 'Activity ID', 'Student ID', 'Amount', 'Status'], ['CF1', 'A1', 'S1', '20', 'Pending']],
            'Configuration' => [['Key', 'Value'], ['bankName', 'Bank of Maldives'], ['secretThing', 'x']],
            'UserPermissions' => [['User ID', 'Permission'], ['U1', 'canVerifyPayments']],
            'AnnualFeeConfig' => [['Year', 'Amount', 'Status'], ['2026', '150', 'Active']],
            'AnnualFees' => [['ID', 'Year', 'Student ID', 'Amount'], ['AF1', '2026', 'S1', '150']],
            'Payments' => [
                ['ID', 'Type', 'Payable ID', 'Amount', 'Method', 'Status'],
                ['P1', 'Class Fee', 'CF1', '15', 'cash', 'Approved'],
                ['P2', 'Annual Fee', 'AF1', '50', 'online', 'Rejected'],
            ],
            'ShopItems' => [['ID', 'Name', 'Price', 'Stock'], ['I1', 'Scarf', '85', '10']],
            'Purchases' => [['ID', 'Student ID', 'Item ID', 'Quantity', 'Purchase Status'], ['PU1', 'S1', 'I1', '2', 'Delivered']],
        ];

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $title => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);
            $sheet->fromArray($rows);
        }

        $path = tempnam(sys_get_temp_dir(), 'legacy').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_inspect_lists_sheets_and_missing_identity_sheets(): void
    {
        $inspection = app(LegacyImportService::class)->inspect(self::demoWorkbook());

        $this->assertSame([], $inspection['missing_identity']);
        $students = collect($inspection['sheets'])->firstWhere('name', 'Students');
        $this->assertSame(3, $students['rows']);
        $this->assertSame(3, $students['valid']);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $result = app(LegacyImportService::class)->import(self::demoWorkbook(), true);

        $this->assertTrue($result['dry_run']);
        $this->assertSame(3, $result['counts']['Students']['imported']);
        $this->assertSame(0, Student::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'import.legacy']);
    }

    public function test_sample_workbook_has_dropdowns_and_imports_cleanly(): void
    {
        $response = $this->actingAs($this->admin())->get('/import/template');
        $response->assertOk();
        $path = $response->getFile()->getPathname();

        $students = IOFactory::load($path)->getSheetByName('Students');
        $section = array_search('Section', $students->toArray()[0], true);
        $this->assertSame(DataValidation::TYPE_LIST, $students->getCell(Coordinate::stringFromColumnIndex($section + 1).'2')->getDataValidation()->getType());

        $result = app(LegacyImportService::class)->import($path, true);
        $this->assertSame([], $result['errors']);
        $this->assertSame(0, Student::query()->count());
    }

    public function test_real_import_maps_legacy_ids_and_rules(): void
    {
        $admin = $this->admin();
        $result = app(LegacyImportService::class)->import(self::demoWorkbook(), false, $admin);

        $ali = Student::query()->where('national_id', 'A1111111')->firstOrFail();
        $this->assertSame('Scout', $ali->section->value);
        $this->assertSame('2012-02-01', $ali->date_of_birth->toDateString());
        $this->assertSame('Cub Scout', Student::query()->where('national_id', 'A2222222')->firstOrFail()->section->value);

        $leader = User::query()->where('national_id', 'A9000001')->firstOrFail();
        $this->assertNotNull($leader->legacy_pin_hash);
        $this->assertTrue($leader->hasPermission('canVerifyPayments'));
        $this->assertTrue(Hash::check('4321', User::query()->where('national_id', 'A9000002')->value('password')));

        $noPin = User::query()->where('national_id', 'A9000003')->firstOrFail();
        $this->assertSame(UserStatus::Inactive, $noPin->status);
        $this->assertFalse(Hash::check('2468', $noPin->password));

        $this->assertDatabaseMissing('users', ['national_id' => 'A9000004']);
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => $e['sheet'] === 'Users' && str_contains($e['message'], 'Unknown role')));
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => $e['sheet'] === 'GroupAssistantLeaders' && str_contains($e['message'], 'Rover')));
        $this->assertSame($ali->id, User::query()->where('national_id', 'A1111111')->value('student_id'));

        $group = Group::query()->where('legacy_id', 'G1')->firstOrFail();
        $this->assertSame(2, $group->members()->count());
        $this->assertSame(1, $group->assistantLeaders()->count());
        $this->assertSame(1, $group->leaders()->count());

        $activity = Activity::query()->where('legacy_id', 'A1')->firstOrFail();
        $this->assertSame('2026-03-12', $activity->date->toDateString());
        $this->assertSame([$group->id], $activity->groups->pluck('id')->all());
        $this->assertSame(2, AttendanceRecord::query()->count());

        $fee = ClassFee::query()->where('legacy_id', 'CF1')->firstOrFail();
        $this->assertSame('15.00', $fee->paid_amount);
        $this->assertSame(FeeStatus::Partial, $fee->status);
        $this->assertSame('0.00', AnnualFee::query()->firstOrFail()->paid_amount);
        $this->assertSame('Rejected', Payment::query()->where('legacy_id', 'P2')->firstOrFail()->status->value);

        $this->assertSame('Bank of Maldives', app(SettingsService::class)->bankName());
        $this->assertSame(10, ShopItem::query()->firstOrFail()->stock_qty);
        $this->assertTrue(Purchase::query()->firstOrFail()->stock_decremented);
        $this->assertDatabaseHas('audit_logs', ['action' => 'import.legacy']);
    }

    public function test_import_is_idempotent(): void
    {
        $path = self::demoWorkbook();
        app(LegacyImportService::class)->import($path, false);
        app(LegacyImportService::class)->import($path, false);

        $this->assertSame(3, Student::query()->count());
        $this->assertSame(1, ClassFee::query()->count());
        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(1, Purchase::query()->count());
    }

    public function test_legacy_pin_works_once_imported(): void
    {
        app(LegacyImportService::class)->import(self::demoWorkbook(), false);

        $this->post('/login', ['national_id' => 'A9000001', 'pin' => '1234']);

        $this->assertAuthenticatedAs(User::query()->where('national_id', 'A9000001')->firstOrFail());
    }

    public function test_admin_import_flow_with_error_csv_and_cleanup(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $file = new UploadedFile(self::demoWorkbook(), 'legacy.xlsx', null, null, true);

        $this->actingAs($admin)->post('/import/preview', ['file' => $file])->assertRedirect(route('import.index'));
        $this->assertSame(0, Student::query()->count());
        $this->actingAs($admin)->get('/import')->assertOk()->assertSee('Dry run result');

        $csv = $this->actingAs($admin)->get('/import/errors');
        $csv->assertOk();
        $this->assertStringContainsString('Unknown role', $csv->streamedContent());

        $this->actingAs($admin)->post('/import/confirm')->assertSessionHas('success');
        $this->assertSame(3, Student::query()->count());
        $this->assertSame([], Storage::disk('local')->files('imports'));

        $this->actingAs($this->leader())->get('/import')->assertForbidden();
    }
}
