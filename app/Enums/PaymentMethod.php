<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentMethod: string
{
    use HasOptions;

    case Cash = 'cash';
    case Online = 'online';

    public function requiresProof(): bool
    {
        return $this === self::Online;
    }

    public function labelText(): string
    {
        return $this === self::Cash ? 'Cash' : 'Online transfer';
    }
}
