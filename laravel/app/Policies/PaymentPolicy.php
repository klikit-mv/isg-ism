<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AnnualFee;
use App\Models\Payment;
use App\Models\User;
use App\Services\LeaderScopeService;

class PaymentPolicy
{
    public function __construct(private LeaderScopeService $scope) {}

    public function view(User $user, Payment $payment): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->hasPermission(Permission::VerifyPayments) || $payment->submitted_by === $user->id) {
            return true;
        }

        if ($payment->payable instanceof AnnualFee && $payment->payable->user_id === $user->id) {
            return true;
        }

        return $payment->student !== null && $this->scope->canAccessStudent($user, $payment->student);
    }

    public function verify(User $user): bool
    {
        return $user->isActive() && $user->hasPermission(Permission::VerifyPayments);
    }
}
