<?php

namespace App\Policies;

use App\Models\CertificateTemplate;
use App\Models\User;

class CertificateTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->isLeader();
    }

    public function view(User $user, CertificateTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CertificateTemplate $template): bool
    {
        return false;
    }

    public function delete(User $user, CertificateTemplate $template): bool
    {
        return false;
    }
}
