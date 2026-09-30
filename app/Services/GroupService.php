<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Enums\Role;
use App\Enums\ScoutSection;
use App\Exceptions\ScoutException;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GroupService
{
    public function __construct(private AuditLogService $audit) {}

    public function create(string $name, ?string $type, User $actor, ?ScoutSection $section = null): Group
    {
        return DB::transaction(function () use ($name, $type, $actor, $section): Group {
            $group = Group::query()->create([
                'name' => $name,
                'type' => $type,
                'section' => $section,
                'owner_id' => $actor->id,
                'status' => RecordStatus::Active,
            ]);
            $group->leaders()->attach($actor->id);

            $this->audit->record('group.created', $group, ['name' => $name, 'type' => $type, 'section' => $section], $actor);

            return $group;
        });
    }

    public function rename(Group $group, string $name, ?string $type, User $actor, ?ScoutSection $section = null): void
    {
        $from = $group->name;
        $group->update(['name' => $name, 'type' => $type, 'section' => $section]);
        $this->audit->record('group.renamed', $group, ['from' => $from, 'to' => $name], $actor);
    }

    public function delete(Group $group, User $actor): void
    {
        $this->audit->record('group.deleted', $group, ['name' => $group->name], $actor);
        $group->delete();
    }

    /**
     * Replace members, leaders and Rover assistant leaders in one transaction.
     *
     * @param  list<int>  $memberIds
     * @param  list<int>  $leaderIds
     * @param  list<int>  $assistantIds
     */
    public function syncMembership(Group $group, array $memberIds, array $leaderIds, array $assistantIds, User $actor): void
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        $leaderIds = array_values(array_unique(array_map('intval', $leaderIds)));
        $assistantIds = array_values(array_unique(array_map('intval', $assistantIds)));

        $leaders = User::query()->whereIn('id', $leaderIds)->with('roleRows')->get();

        if ($leaders->count() !== count($leaderIds) || $leaders->contains(fn (User $u) => ! $u->hasAnyRole([Role::Leader, Role::Admin]))) {
            throw new ScoutException('Only users with the leader role can be assigned as group leaders.');
        }

        $rovers = Student::query()->whereIn('id', $assistantIds)->get();

        if ($rovers->count() !== count($assistantIds) || $rovers->contains(fn (Student $s) => $s->section !== ScoutSection::Rover)) {
            throw new ScoutException('Assistant leaders must be Rover scouts.');
        }

        $members = Student::query()->whereIn('id', $memberIds)->get();

        if ($members->count() !== count($memberIds)) {
            throw new ScoutException('One of the selected members no longer exists.');
        }

        if ($group->section !== null && ($outside = $members->first(fn (Student $s) => $s->section !== $group->section))) {
            throw new ScoutException("{$outside->name} is not in the {$group->section->value} section, so cannot join this group.");
        }

        DB::transaction(function () use ($group, $memberIds, $leaderIds, $assistantIds, $actor): void {
            $group->members()->sync($memberIds);
            $group->leaders()->sync($leaderIds);
            $group->assistantLeaders()->sync($assistantIds);

            $this->audit->record('group.membership_synced', $group, [
                'members' => count($memberIds),
                'leaders' => count($leaderIds),
                'assistant_leaders' => count($assistantIds),
            ], $actor);
        });
    }
}
