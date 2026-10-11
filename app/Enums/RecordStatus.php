<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RecordStatus: string
{
    use HasOptions;

    case Active = 'Active';
    case Inactive = 'Inactive';

    public function labelText(): string
    {
        return $this->value;
    }

    public function tone(): string
    {
        return $this === self::Active ? 'green' : 'gray';
    }
}
