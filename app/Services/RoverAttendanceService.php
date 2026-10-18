<?php

namespace App\Services;

use App\Enums\RoverAttendanceStatus;
use App\Enums\ScoutSection;
use App\Enums\StudentStatus;
use App\Exceptions\ScoutException;
use App\Exceptions\StudentNotAccessible;
use App\Models\Activity;
use App\Models\RoverAttendanceRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Separate Rover register. Rover attendance never creates fees.
 */
class RoverAttendanceService
{
    public function __construct(private ActivityRosterService $roster, private AuditLogService $audit) {}

    /**
     * @return array{required: Collection<int, Student>, optional: Collection<int, Student>, additional: Collection<int, Student>, available: Collection<int, Student>}
     */
    public function participants(Activity $activity): array
    {
        $activeRovers = Student::query()->where('section', ScoutSection::Rover->value)->where('status', StudentStatus::Active->value)->orderBy('name')->get();
        $rosterIds = $this->roster->resolveStudentIds($activity);

        $required = $activeRovers->filter(fn (Student $s) => in_array($s->id, $rosterIds, true))->values();

        $assistantIds = DB::table('group_assistant_leaders')
            ->join('activity_groups', 'activity_groups.group_id', '=', 'group_assistant_leaders.group_id')
            ->where('activity_groups.activity_id', $activity->id)
            ->pluck('group_assistant_leaders.student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $optional = $activeRovers->filter(fn (Student $s) => in_array($s->id, $assistantIds, true) && ! $required->contains($s))->values();

        $recordedIds = RoverAttendanceRecord::query()->where('activity_id', $activity->id)->pluck('student_id')->map(fn ($id) => (int) $id)->all();
        $listed = $required->pluck('id')->merge($optional->pluck('id'))->all();

        $additional = Student::query()
            ->whereIn('id', array_diff($recordedIds, $listed) ?: [0])
            ->where('section', ScoutSection::Rover->value)
            ->orderBy('name')
            ->get();

        $taken = array_merge($listed, $additional->pluck('id')->all());
        $available = $activeRovers->reject(fn (Student $s) => in_array($s->id, $taken, true))->values();

        return [
            'required' => new Collection($required->all()),
            'optional' => new Collection($optional->all()),
            'additional' => $additional,
            'available' => new Collection($available->all()),
        ];
    }

    /**
     * @param  array<int|string, ?string>  $marks  student id => status
     * @return array{marked: int}
     */
    public function mark(Activity $activity, User $actor, array $marks): array
    {
        $this->roster->verifyLeaderCanManageActivity($activity, $actor);
        $participants = $this->participants($activity);
        $requiredIds = $participants['required']->pluck('id')->all();
        $knownIds = array_merge(
            $requiredIds,
            $participants['optional']->pluck('id')->all(),
            $participants['additional']->pluck('id')->all(),
            $participants['available']->pluck('id')->all(),
        );

        return DB::transaction(function () use ($activity, $actor, $marks, $requiredIds, $knownIds): array {
            $marked = 0;

            foreach ($marks as $studentId => $value) {
                $status = RoverAttendanceStatus::tryFrom((string) $value);

                if ($status === null) {
                    continue;
                }

                $studentId = (int) $studentId;

                if (! in_array($studentId, $knownIds, true)) {
                    throw new StudentNotAccessible('One of the selected people is not an active Rover. Nothing was saved.');
                }

                $isRequired = in_array($studentId, $requiredIds, true);

                if (! $isRequired && ! $status->allowedForOptional()) {
                    throw new ScoutException('Optional Rovers can only be marked Present.');
                }

                RoverAttendanceRecord::query()->updateOrCreate(
                    ['activity_id' => $activity->id, 'student_id' => $studentId],
                    ['status' => $status, 'is_required' => $isRequired, 'marked_by' => $actor->id, 'marked_at' => now()],
                );

                $this->audit->record('rover_attendance.marked', $activity, ['student_id' => $studentId, 'status' => $status, 'required' => $isRequired], $actor);
                $marked++;
            }

            return ['marked' => $marked];
        });
    }
}
