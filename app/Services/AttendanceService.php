<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Exceptions\StudentNotAccessible;
use App\Models\Activity;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        private ActivityRosterService $roster,
        private ClassFeeService $classFees,
        private AuditLogService $audit,
    ) {}

    /**
     * Upsert marks (blank statuses are skipped), sync class fees and record
     * roster cash in one transaction. Any student outside the roster or the
     * actor's scope fails the whole save.
     *
     * @param  array<int|string, array{status?: ?string, remarks?: ?string, payment?: ?string}>  $marks  keyed by student id
     * @return array{marked: int}
     */
    public function mark(Activity $activity, User $actor, array $marks): array
    {
        $this->roster->verifyLeaderCanManageActivity($activity, $actor);
        $allowed = $this->roster->markableStudentIds($activity, $actor);

        return DB::transaction(function () use ($activity, $actor, $marks, $allowed): array {
            $marked = 0;

            foreach ($marks as $studentId => $mark) {
                $status = AttendanceStatus::tryFrom((string) ($mark['status'] ?? ''));

                if ($status === null) {
                    continue;
                }

                if (! in_array((int) $studentId, $allowed, true)) {
                    throw new StudentNotAccessible('One of the scouts is not on this roster or not in your groups. Nothing was saved.');
                }

                $student = Student::query()->findOrFail($studentId);

                AttendanceRecord::query()->updateOrCreate(
                    ['activity_id' => $activity->id, 'student_id' => $student->id],
                    [
                        'status' => $status,
                        'remarks' => filled($mark['remarks'] ?? null) ? mb_substr((string) $mark['remarks'], 0, 255) : null,
                        'marked_by' => $actor->id,
                        'marked_at' => now(),
                    ],
                );

                $fee = $this->classFees->syncForAttendance($activity, $student, $status, $actor);

                if ($fee !== null && $status !== AttendanceStatus::Excused && array_key_exists('payment', $mark) && $mark['payment'] !== null && $mark['payment'] !== '') {
                    $this->classFees->settleRosterPayment($fee, (string) $mark['payment'], $actor);
                }

                $this->audit->record('attendance.marked', $activity, ['student' => $student->uuid, 'status' => $status], $actor);
                $marked++;
            }

            return ['marked' => $marked];
        });
    }
}
