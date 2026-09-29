<?php

namespace App\Policies;

use App\Models\LeadershipRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\LeaderScopeService;

class LeadershipRecordPolicy
{
    public function __construct(private LeaderScopeService $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, LeadershipRecord $record): bool
    {
        return $user->isActive() && $this->scope->canAccessStudent($user, $record->student);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->isLeader();
    }

    public function createFor(User $user, Student $student): bool
    {
        return $this->create($user) && $this->scope->canAccessStudent($user, $student);
    }

    public function update(User $user, LeadershipRecord $record): bool
    {
        return $user->isActive() && $user->isLeader() && $this->scope->canAccessStudent($user, $record->student);
    }

    public function delete(User $user, LeadershipRecord $record): bool
    {
        return $this->update($user, $record);
    }
}
