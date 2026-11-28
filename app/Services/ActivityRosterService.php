<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Exceptions\ActivityNotAccessible;
use App\Exceptions\StudentNotAccessible;
use App\Models\Activity;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * An activity roster is the de-duplicated union of all students (when
 * targeted), selected sections and selected groups — active scouts only.
 */
class ActivityRosterService
{
    public function __construct(private LeaderScopeService $scope) {}

    /**
     * @return list<int>
     */
    public function resolveStudentIds(Activity $activity): array
    {
        $active = StudentStatus::Active->value;
        $ids = [];

        if ($activity->all_students) {
            $ids = Student::query()->where('status', $active)->pluck('id')->all();
        }

        $sections = DB::table('activity_sections')->where('activity_id', $activity->id)->pluck('section')->all();

        if ($sections !== []) {
            $ids = array_merge($ids, Student::query()->where('status', $active)->whereIn('section', $sections)->pluck('id')->all());
        }

        $groupIds = DB::table('activity_groups')
            ->join('groups', 'groups.id', '=', 'activity_groups.group_id')
            ->whereNull('groups.deleted_at')
            ->where('activity_groups.activity_id', $activity->id)
            ->pluck('activity_groups.group_id')
            ->all();

        if ($groupIds !== []) {
            $ids = array_merge($ids, Student::query()
                ->where('status', $active)
                ->whereIn('id', DB::table('group_members')->whereIn('group_id', $groupIds)->select('student_id'))
                ->pluck('id')
                ->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @return Collection<int, Student>
     */
    public function resolveRoster(Activity $activity): Collection
    {
        $ids = $this->resolveStudentIds($activity);

        return Student::query()->whereIn('id', $ids === [] ? [0] : $ids)->orderBy('name')->get();
    }

    /**
     * Roster scouts this user may mark (leaders: only their own scouts).
     *
     * @return list<int>
     */
    public function markableStudentIds(Activity $activity, User $user): array
    {
        $roster = $this->resolveStudentIds($activity);
        $scoped = $this->scope->getLeaderStudentIds($user);

        return $scoped === null ? $roster : array_values(array_intersect($roster, $scoped));
    }

    public function verifyStudentBelongsToActivity(Activity $activity, Student $student): void
    {
        if (! in_array($student->id, $this->resolveStudentIds($activity), true)) {
            throw new StudentNotAccessible("{$student->name} is not on the roster for this activity.");
        }
    }

    public function verifyLeaderCanManageActivity(Activity $activity, User $user): void
    {
        if (! $this->scope->canManageAttendance($user, $activity)) {
            throw new ActivityNotAccessible('You cannot manage attendance for this activity.');
        }
    }
}
