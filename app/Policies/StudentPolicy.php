<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Services\LeaderScopeService;

/**
 * Admins pass every check through Gate::before.
 */
class StudentPolicy
{
    public function __construct(private LeaderScopeService $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Student $student): bool
    {
        return $user->isActive() && $this->scope->canAccessStudent($user, $student);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Student $student): bool
    {
        return false;
    }

    public function delete(User $user, Student $student): bool
    {
        return false;
    }

    public function import(User $user): bool
    {
        return $user->isActive() && $user->isLeader();
    }

    public function photo(User $user, Student $student): bool
    {
        return $user->isActive() && $user->isLeader() && $this->scope->canAccessStudent($user, $student);
    }

    /**
     * Any leader may verify or decline a pending registration.
     */
    public function verify(User $user, Student $student): bool
    {
        return $user->isActive() && $user->isLeader();
    }

    public function promote(User $user): bool
    {
        return false;
    }
}
