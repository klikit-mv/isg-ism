<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Purchase;
use App\Models\User;
use App\Services\LeaderScopeService;

class PurchasePolicy
{
    public function __construct(private LeaderScopeService $scope) {}

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->isActive() && ($user->hasPermission(Permission::ManageShop) || $this->scope->canAccessStudent($user, $purchase->student));
    }

    /**
     * The buyer side (anyone who can act for the scout) or shop staff.
     */
    public function cancel(User $user, Purchase $purchase): bool
    {
        return $this->view($user, $purchase);
    }
}
