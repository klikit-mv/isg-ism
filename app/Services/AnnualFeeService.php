<?php

namespace App\Services;

use App\Enums\FeeStatus;
use App\Enums\PersonType;
use App\Enums\RecordStatus;
use App\Enums\Role;
use App\Enums\ScoutSection;
use App\Enums\StudentStatus;
use App\Exceptions\ScoutException;
use App\Models\AnnualFee;
use App\Models\AnnualFeeYear;
use App\Models\Student;
use App\Models\User;
use App\Support\Import\SpreadsheetReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnnualFeeService
{
    public function __construct(private AuditLogService $audit) {}

    public function createYear(int $year, string $amount, User $actor): AnnualFeeYear
    {
        $feeYear = AnnualFeeYear::query()->create([
            'year' => $year,
            'amount' => $amount,
            'status' => RecordStatus::Active,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $this->audit->record('annual_fee_year.created', $feeYear, ['year' => $year, 'amount' => $amount], $actor);

        return $feeYear;
    }

    public function setYearStatus(AnnualFeeYear $year, RecordStatus $status, User $actor): void
    {
        $year->update(['status' => $status, 'updated_by' => $actor->id]);
        $this->audit->record('annual_fee_year.status_changed', $year, ['status' => $status], $actor);
    }

    public function suggestedYear(): int
    {
        $used = AnnualFeeYear::query()->pluck('year')->all();
        $year = (int) scout_now()->format('Y');

        while (in_array($year, $used, true)) {
            $year++;
        }

        return $year;
    }

    /**
     * Every scout and leader who could be invoiced.
     *
     * @return Collection<int, array{key: string, type: PersonType, id: int, name: string, national_id: string, section: ?string, invoiced: bool}>
     */
    public function invoicePeople(AnnualFeeYear $year, bool $includeInactive = false): Collection
    {
        $studentIds = AnnualFee::query()->where('annual_fee_year_id', $year->id)->whereNotNull('student_id')->pluck('student_id')->all();
        $userIds = AnnualFee::query()->where('annual_fee_year_id', $year->id)->whereNotNull('user_id')->pluck('user_id')->all();

        $students = Student::query()
            ->when(! $includeInactive, fn ($q) => $q->where('status', StudentStatus::Active->value))
            ->orderBy('name')
            ->get()
            ->map(fn (Student $s) => [
                'key' => 's'.$s->id,
                'type' => PersonType::Student,
                'id' => $s->id,
                'name' => $s->name,
                'national_id' => $s->national_id,
                'section' => $s->section?->value,
                'invoiced' => in_array($s->id, $studentIds, true),
            ]);

        $leaders = User::query()
            ->withRole(Role::Leader)
            ->when(! $includeInactive, fn ($q) => $q->active())
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'key' => 'u'.$u->id,
                'type' => PersonType::Leader,
                'id' => $u->id,
                'name' => $u->name,
                'national_id' => $u->national_id,
                'section' => null,
                'invoiced' => in_array($u->id, $userIds, true),
            ]);

        return $students->concat($leaders)->values();
    }

    /**
     * Match the rows of an Excel/CSV list to people by National ID (when the sheet has that column) or by name, so a
     * ready-made list can be turned into invoices after a preview.
     *
     * @return array{rows: int, matched: list<array<string, mixed>>, invoiced: list<array<string, mixed>>, problems: list<array{row: int, name: string, reason: string}>, columns: array{name: ?string, national_id: ?string, section: ?string}}
     */
    public function matchSpreadsheet(AnnualFeeYear $year, string $path, bool $includeInactive = false): array
    {
        $sheets = SpreadsheetReader::read($path);
        $sheet = reset($sheets) ?: ['headers' => [], 'rows' => []];
        $headers = $sheet['headers'];

        $pick = fn (array $names) => collect($names)->first(fn (string $n) => in_array($n, $headers, true));
        $nameColumn = $pick(['name', 'fullname', 'studentname', 'scoutname', 'membername', 'student', 'scout', 'member']) ?? ($headers[0] ?? null);
        $idColumn = $pick(['nationalid', 'idcardno', 'idcard', 'idno', 'nid', 'id']);
        $sectionColumn = $pick(['section']);

        $people = $this->invoicePeople($year, $includeInactive);
        $byId = $people->filter(fn (array $p) => filled($p['national_id']))->groupBy(fn (array $p) => strtoupper(trim((string) $p['national_id'])));
        $byName = $people->groupBy(fn (array $p) => $this->normalizeName($p['name']));

        $matched = [];
        $invoiced = [];
        $problems = [];
        $seen = [];

        foreach ($sheet['rows'] as $index => $row) {
            $number = $index + 2;
            $name = trim((string) ($row[$nameColumn] ?? ''));
            $id = $idColumn ? strtoupper(trim((string) ($row[$idColumn] ?? ''))) : '';

            if ($name === '' && $id === '') {
                continue;
            }

            $candidates = $id !== '' ? ($byId->get($id) ?? collect()) : collect();

            if ($candidates->isEmpty() && $name !== '') {
                $candidates = $byName->get($this->normalizeName($name)) ?? collect();
            }

            $label = $name !== '' ? $name : $id;

            if ($candidates->isEmpty()) {
                $problems[] = ['row' => $number, 'name' => $label, 'reason' => $includeInactive ? 'No scout or leader with this name.' : 'No active scout or leader with this name.'];

                continue;
            }

            if ($candidates->count() > 1) {
                $problems[] = ['row' => $number, 'name' => $label, 'reason' => 'More than one person has this name. Add a National ID column to the list.'];

                continue;
            }

            $person = $candidates->first();

            if (isset($seen[$person['key']])) {
                $problems[] = ['row' => $number, 'name' => $label, 'reason' => 'Listed more than once; counted once.'];

                continue;
            }

            $seen[$person['key']] = true;
            $person['file_name'] = $name;
            $person['row'] = $number;
            $person['file_section'] = $sectionColumn ? (ScoutSection::tryFrom(trim((string) ($row[$sectionColumn] ?? '')))?->value) : null;

            $person['invoiced'] ? $invoiced[] = $person : $matched[] = $person;
        }

        return [
            'rows' => count($sheet['rows']),
            'matched' => $matched,
            'invoiced' => $invoiced,
            'problems' => $problems,
            'columns' => ['name' => $nameColumn, 'national_id' => $idColumn, 'section' => $sectionColumn],
        ];
    }

    private function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($name))));
    }

    /**
     * Idempotent: skip anyone already invoiced for the year.
     *
     * @param  list<array{type: string, id: int, section?: ?string}>  $people
     * @return array{created: int, skipped: int}
     */
    public function generate(AnnualFeeYear $year, array $people, User $actor): array
    {
        if ($year->status !== RecordStatus::Active) {
            throw new ScoutException('Only Active annual fee years can have fees generated.');
        }

        return DB::transaction(function () use ($year, $people, $actor): array {
            $created = 0;
            $skipped = 0;

            foreach ($people as $person) {
                $type = PersonType::tryFrom($person['type']) ?? PersonType::Student;
                $column = $type === PersonType::Leader ? 'user_id' : 'student_id';

                $exists = AnnualFee::query()->where('annual_fee_year_id', $year->id)->where($column, $person['id'])->lockForUpdate()->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $section = $type === PersonType::Student
                    ? (ScoutSection::tryFrom((string) ($person['section'] ?? '')) ?? Student::query()->find($person['id'])?->section)
                    : null;

                AnnualFee::query()->create([
                    'annual_fee_year_id' => $year->id,
                    $column => $person['id'],
                    'person_type' => $type,
                    'section' => $section,
                    'amount' => $year->amount,
                    'paid_amount' => '0.00',
                    'outstanding_amount' => $year->amount,
                    'status' => FeeStatus::Pending,
                    'created_by' => $actor->id,
                ]);
                $created++;
            }

            $this->audit->record('annual_fee.generated', $year, ['created' => $created, 'skipped' => $skipped], $actor);

            return ['created' => $created, 'skipped' => $skipped];
        });
    }
}
