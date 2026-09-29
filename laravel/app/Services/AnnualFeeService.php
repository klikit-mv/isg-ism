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
