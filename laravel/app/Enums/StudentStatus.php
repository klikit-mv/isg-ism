<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StudentStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Pending = 'pending';
    case Inactive = 'inactive';

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Pending => 'amber',
            self::Inactive => 'gray',
        };
    }
}
