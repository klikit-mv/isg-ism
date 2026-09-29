<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Services\LeaderScopeService;

class ActivityPolicy
{
    public function __construct(private LeaderScopeService $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->isLeader();
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->isActive() && $this->scope->canAccessActivity($user, $activity);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->isLeader();
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->isActive() && $user->isLeader() && $this->scope->canAccessActivity($user, $activity);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function markAttendance(User $user, Activity $activity): bool
    {
        return $user->isActive() && $this->scope->canManageAttendance($user, $activity);
    }
}
