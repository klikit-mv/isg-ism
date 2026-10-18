<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Role: string
{
    use HasOptions;

    case Admin = 'admin';
    case Leader = 'leader';
    case Parent = 'parent';
    case Student = 'student';

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access, including users, settings, import, audit and section promotion.',
            self::Leader => 'Runs day-to-day operations for the groups they lead; can verify registrations.',
            self::Parent => 'Sees and pays for approved linked children only.',
            self::Student => 'Sees their own record, fees, certificates and shop.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Admin => 'purple',
            self::Leader => 'blue',
            self::Parent => 'amber',
            self::Student => 'green',
        };
    }
}
