<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\ScoutSection;
use App\Enums\StudentStatus;
use App\Enums\UserStatus;
use App\Exceptions\ScoutException;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Scout lifecycle. Every scout has a linked student account that mirrors
 * name, National ID, email and active/inactive status.
 */
class StudentService
{
    public const STUDENT_FIELDS = [
        'index_number', 'name', 'national_id', 'email', 'gender', 'permanent_address', 'present_address',
        'date_of_birth', 'parent_name', 'primary_mobile', 'secondary_mobile', 'section', 'status',
    ];

    public function __construct(
        private AuditLogService $audit,
        private NotificationService $notifications,
    ) {}

    public function temporaryPin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Admin enrolment. Returns the student and the PIN that was set.
     *
     * @param  array<string, mixed>  $data
     * @return array{student: Student, pin: string}
     */
    public function create(array $data, ?User $actor): array
    {
        return DB::transaction(function () use ($data, $actor): array {
            $pin = filled($data['pin'] ?? null) ? (string) $data['pin'] : $this->temporaryPin();
            $attributes = $this->studentAttributes($data);
            $attributes['status'] ??= StudentStatus::Active->value;

            if ($attributes['status'] === StudentStatus::Active->value) {
                $attributes['verified_at'] = now();
                $attributes['verified_by'] = $actor?->id;
            }

            $student = Student::query()->create($attributes);

            $user = User::query()->create([
                'name' => $student->name,
                'national_id' => $student->national_id,
                'email' => $student->email,
                'password' => $pin,
                'status' => $student->status === StudentStatus::Active ? UserStatus::Active : UserStatus::Inactive,
                'student_id' => $student->id,
                'verified_at' => $student->verified_at,
                'verified_by' => $actor?->id,
            ]);
            $user->assignRole(Role::Student);

            $this->audit->record('student.created', $student, ['national_id' => $student->national_id, 'section' => $student->section], $actor);
            $this->audit->record('user.created', $user, ['national_id' => $user->national_id, 'roles' => [Role::Student->value]], $actor);

            return ['student' => $student, 'pin' => $pin];
        });
    }

    /**
     * Public self-registration: a pending scout with an inactive account.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): Student
    {
        $student = DB::transaction(function () use ($data): Student {
            $attributes = $this->studentAttributes($data);
            $attributes['status'] = StudentStatus::Pending->value;

            $student = Student::query()->create($attributes);

            $user = User::query()->create([
                'name' => $student->name,
                'national_id' => $student->national_id,
                'email' => $student->email,
                'password' => (string) $data['pin'],
                'status' => UserStatus::Inactive,
                'student_id' => $student->id,
            ]);
            $user->assignRole(Role::Student);

            $this->audit->record('student.registered', $student, ['national_id' => $student->national_id, 'section' => $student->section], $user);

            return $student;
        });

        $this->notifications->studentRegistered($student);

        return $student;
    }

    public function verifyRegistration(Student $student, User $actor): void
    {
        DB::transaction(function () use ($student, $actor): void {
            $student->update([
                'status' => StudentStatus::Active,
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ]);

            $student->user?->forceFill([
                'status' => UserStatus::Active,
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ])->save();

            $this->audit->record('student.verified', $student, [], $actor);
        });

        $this->notifications->studentVerified($student->fresh());
    }

    public function rejectRegistration(Student $student, User $actor): void
    {
        DB::transaction(function () use ($student, $actor): void {
            $student->update(['status' => StudentStatus::Inactive]);
            $student->user?->forceFill([
                'status' => UserStatus::Inactive,
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ])->save();

            $this->audit->record('student.declined', $student, [], $actor);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data, User $actor): Student
    {
        return DB::transaction(function () use ($student, $data, $actor): Student {
            $student->fill($this->studentAttributes($data));
            $changes = array_keys($student->getDirty());
            $student->save();

            $this->mirrorAccount($student);
            $this->audit->record('student.updated', $student, ['changed' => $changes], $actor);

            return $student;
        });
    }

    public function setStatus(Student $student, StudentStatus $status, User $actor): void
    {
        DB::transaction(function () use ($student, $status, $actor): void {
            $student->update(['status' => $status]);
            $this->mirrorAccount($student);
            $this->audit->record('student.status_changed', $student, ['status' => $status], $actor);
        });
    }

    public function delete(Student $student, User $actor): void
    {
        DB::transaction(function () use ($student, $actor): void {
            $this->audit->record('student.deleted', $student, ['national_id' => $student->national_id], $actor);
            $student->user?->delete();
            $student->delete();
        });
    }

    /**
     * Move scouts one section forward. Issued certificates are never rewritten.
     *
     * @param  list<int|string>  $studentIds
     * @return array{promoted: int, skipped: int}
     */
    public function bulkPromote(array $studentIds, ScoutSection $from, ScoutSection $to, User $actor): array
    {
        if ($from->next() !== $to) {
            throw new ScoutException("Scouts can only move one section forward, from {$from->value} to ".($from->next()?->value ?? 'nowhere').'.');
        }

        return DB::transaction(function () use ($studentIds, $from, $to, $actor): array {
            $promoted = 0;
            $skipped = 0;
            $students = Student::query()->whereIn('id', $studentIds)->lockForUpdate()->get();
            $skipped += count(array_unique($studentIds)) - $students->count();

            foreach ($students as $student) {
                if ($student->section !== $from) {
                    $skipped++;

                    continue;
                }

                $student->update(['section' => $to]);
                $this->audit->record('student.promoted', $student, ['from' => $from, 'to' => $to], $actor);
                $promoted++;
            }

            return ['promoted' => $promoted, 'skipped' => $skipped];
        });
    }

    private function mirrorAccount(Student $student): void
    {
        $user = $student->user;

        if ($user === null) {
            return;
        }

        $user->fill([
            'name' => $student->name,
            'national_id' => $student->national_id,
            'email' => $student->email,
        ]);

        if ($student->status === StudentStatus::Active) {
            $user->status = UserStatus::Active;
        } elseif ($student->status === StudentStatus::Inactive) {
            $user->status = UserStatus::Inactive;
        }

        $user->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function studentAttributes(array $data): array
    {
        $attributes = array_intersect_key($data, array_flip(self::STUDENT_FIELDS));

        if (isset($attributes['national_id'])) {
            $attributes['national_id'] = Str::upper(trim((string) $attributes['national_id']));
        }

        foreach ($attributes as $key => $value) {
            if ($value instanceof \BackedEnum) {
                $attributes[$key] = $value->value;
            }
        }

        return $attributes;
    }
}
