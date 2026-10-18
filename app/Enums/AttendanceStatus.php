<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AttendanceStatus: string
{
    use HasOptions;

    case Present = 'Present';
    case Late = 'Late';
    case Absent = 'Absent';
    case Excused = 'Excused';

    public function generatesClassFee(): bool
    {
        return $this !== self::Excused;
    }

    public function earnsCertificate(): bool
    {
        return $this === self::Present || $this === self::Late;
    }

    public function labelText(): string
    {
        return $this->value;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Late => 'amber',
            self::Absent => 'red',
            self::Excused => 'blue',
        };
    }
}
