<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EventRegistrationStatus: string
{
    use HasOptions;

    case Registered = 'registered';
    case Cancelled = 'cancelled';

    public function tone(): string
    {
        return $this === self::Registered ? 'green' : 'gray';
    }
}
