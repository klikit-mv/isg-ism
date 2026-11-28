<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PersonType: string
{
    use HasOptions;

    case Student = 'Student';
    case Leader = 'Leader';

    public function labelText(): string
    {
        return $this === self::Student ? 'Scout' : 'Leader';
    }
}
