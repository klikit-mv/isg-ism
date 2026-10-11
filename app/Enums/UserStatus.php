<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UserStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';

    public function tone(): string
    {
        return $this === self::Active ? 'green' : 'gray';
    }
}
