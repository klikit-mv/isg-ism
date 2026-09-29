<?php

namespace App\Policies;

use App\Models\BadgeRequest;
use App\Models\User;
use App\Services\LeaderScopeService;

class BadgeRequestPolicy
{
    public function __construct(private LeaderScopeService $scope) {}

    public function view(User $user, BadgeRequest $request): bool
    {
        return $user->isActive() && $this->scope->canAccessStudent($user, $request->student);
    }

    /**
     * Approve, reject and generate: admin or a scoped leader.
     */
    public function decide(User $user, BadgeRequest $request): bool
    {
        return $user->isActive() && $user->isLeader() && $this->scope->canAccessStudent($user, $request->student);
    }
}
