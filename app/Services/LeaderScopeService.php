<?php

namespace App\Services;

use App\Enums\ParentLinkStatus;
use App\Enums\Permission;
use App\Enums\StudentStatus;
use App\Models\Activity;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The single place where group-scoped visibility is computed.
 */
class LeaderScopeService
{
    /** @var array<int, list<int>> */
    private array $groupCache = [];

    /** @var array<int, list<int>|null> */
    private array $studentCache = [];

    /**
     * Admin: all groups; leader: led groups; others: none.
     *
     * @return list<int>
     */
    public function getLeaderGroupIds(User $user): array
    {
        if ($user->isAdmin()) {
            return Group::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (! $user->isLeader()) {
            return [];
        }

        return $this->groupCache[$user->id] ??= DB::table('group_leaders')
            ->join('groups', 'groups.id', '=', 'group_leaders.group_id')
            ->whereNull('groups.deleted_at')
            ->where('group_leaders.user_id', $user->id)
            ->pluck('group_leaders.group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Admin: null (meaning every student). Otherwise the union of led-group
     * members, approved children and the user's own student record.
     *
     * @return list<int>|null
     */
    public function getLeaderStudentIds(User $user): ?array
    {
        if ($user->isAdmin()) {
            return null;
        }

        if (array_key_exists($user->id, $this->studentCache)) {
            return $this->studentCache[$user->id];
        }

        $ids = [];
        $groupIds = $this->getLeaderGroupIds($user);

        if ($groupIds !== []) {
            $ids = DB::table('group_members')->whereIn('group_id', $groupIds)->pluck('student_id')->all();
        }

        $children = DB::table('parent_student_links')
            ->where('parent_user_id', $user->id)
            ->where('status', ParentLinkStatus::Approved->value)
            ->pluck('student_id')
            ->all();

        $ids = array_merge($ids, $children);

        if ($user->student_id) {
            $ids[] = $user->student_id;
        }

        return $this->studentCache[$user->id] = array_values(array_unique(array_map('intval', $ids)));
    }

    public function forget(?User $user = null): void
    {
        if ($user === null) {
            $this->groupCache = [];
            $this->studentCache = [];

            return;
        }

        unset($this->groupCache[$user->id], $this->studentCache[$user->id]);
    }

    public function canAccessGroup(User $user, Group $group): bool
    {
        return $user->isAdmin() || in_array($group->id, $this->getLeaderGroupIds($user), true);
    }

    public function canAccessStudent(User $user, Student $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isLeader() && $student->status === StudentStatus::Pending) {
            return true;
        }

        return in_array($student->id, $this->getLeaderStudentIds($user) ?? [], true);
    }

    /**
     * Students visible to staff lists: leaders also see every pending registration.
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function constrainStudents(Builder $query, User $user): Builder
    {
        $ids = $this->getLeaderStudentIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($ids, $user): void {
            $q->whereIn('students.id', $ids === [] ? [0] : $ids);

            if ($user->isLeader()) {
                $q->orWhere('students.status', StudentStatus::Pending->value);
            }
        });
    }

    /**
     * Restrict any query with a student_id column to accessible students.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function constrainByStudent(Builder $query, User $user, string $column = 'student_id'): Builder
    {
        $ids = $this->getLeaderStudentIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids === [] ? [0] : $ids);
    }

    /**
     * Leader with at least one group: all-student activities, activities
     * targeting one of their groups, or a section any of their members is in.
     */
    public function canAccessActivity(User $user, Activity $activity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->constrainActivities(Activity::query(), $user)->whereKey($activity->id)->exists();
    }

    /**
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function constrainActivities(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $groupIds = $this->getLeaderGroupIds($user);

        if ($groupIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $memberSections = DB::table('group_members')
            ->join('students', 'students.id', '=', 'group_members.student_id')
            ->whereIn('group_members.group_id', $groupIds)
            ->whereNull('students.deleted_at')
            ->distinct()
            ->pluck('students.section')
            ->all();

        return $query->where(function (Builder $q) use ($groupIds, $memberSections): void {
            $q->where('activities.all_students', true)
                ->orWhereExists(function ($sub) use ($groupIds): void {
                    $sub->selectRaw('1')->from('activity_groups')
                        ->whereColumn('activity_groups.activity_id', 'activities.id')
                        ->whereIn('activity_groups.group_id', $groupIds);
                });

            if ($memberSections !== []) {
                $q->orWhereExists(function ($sub) use ($memberSections): void {
                    $sub->selectRaw('1')->from('activity_sections')
                        ->whereColumn('activity_sections.activity_id', 'activities.id')
                        ->whereIn('activity_sections.section', $memberSections);
                });
            }
        });
    }

    public function canManageAttendance(User $user, Activity $activity): bool
    {
        return ($user->isAdmin() || $user->isLeader()) && $this->canAccessActivity($user, $activity);
    }

    public function canManageFees(User $user): bool
    {
        return $user->hasPermission(Permission::ManageFees);
    }
}
