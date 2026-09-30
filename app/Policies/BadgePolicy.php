<?php

namespace App\Policies;

use App\Models\Badge;
use App\Models\User;

class BadgePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->isLeader();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Badge $badge): bool
    {
        return false;
    }

    public function delete(User $user, Badge $badge): bool
    {
        return false;
    }
}
