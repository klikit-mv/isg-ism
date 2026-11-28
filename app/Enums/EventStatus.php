<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EventStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function labelText(): string
    {
        return match ($this) {
            self::Draft => 'Draft (not visible)',
            self::Open => 'Open for registration',
            self::Closed => 'Registration closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Open => 'green',
            self::Closed => 'amber',
            self::Cancelled => 'red',
        };
    }
}
