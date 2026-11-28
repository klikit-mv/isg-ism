<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Permission: string
{
    use HasOptions;

    case VerifyPayments = 'canVerifyPayments';
    case ManageShop = 'canManageShop';
    case ProcessDelivery = 'canProcessDelivery';
    case ManageFees = 'canManageFees';

    public function labelText(): string
    {
        return match ($this) {
            self::VerifyPayments => 'Verify payments',
            self::ManageShop => 'Manage shop',
            self::ProcessDelivery => 'Process delivery',
            self::ManageFees => 'Manage annual fees',
        };
    }
}
