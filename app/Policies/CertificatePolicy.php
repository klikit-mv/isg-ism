<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;
use App\Services\LeaderScopeService;

class CertificatePolicy
{
    public function __construct(private LeaderScopeService $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Certificate $certificate): bool
    {
        return $user->isActive() && $this->scope->canAccessStudent($user, $certificate->student);
    }

    /**
     * Issue general and bulk certificates.
     */
    public function create(User $user): bool
    {
        return $user->isActive() && $user->isLeader();
    }

    public function manage(User $user, Certificate $certificate): bool
    {
        return $user->isActive() && $user->isLeader() && $this->scope->canAccessStudent($user, $certificate->student);
    }

    public function bulkDownload(User $user): bool
    {
        return $user->isActive() && $user->isLeader();
    }

    /**
     * Only administrators delete a certificate once it exists (admins pass through Gate::before).
     */
    public function delete(User $user, Certificate $certificate): bool
    {
        return false;
    }
}
