<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use App\Services\LeaderScopeService;

class GroupPolicy
{
    public function __construct(private LeaderScopeService $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->isLeader();
    }

    public function view(User $user, Group $group): bool
    {
        return $user->isActive() && $this->scope->canAccessGroup($user, $group);
    }

    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Rename and edit membership: admin or a leader of the group.
     */
    public function update(User $user, Group $group): bool
    {
        return $this->view($user, $group);
    }

    public function delete(User $user, Group $group): bool
    {
        return false;
    }
}
