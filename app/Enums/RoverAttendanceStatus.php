<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RoverAttendanceStatus: string
{
    use HasOptions;

    case Present = 'Present';
    case Absent = 'Absent';
    case Excused = 'Excused';

    public function allowedForOptional(): bool
    {
        return $this === self::Present;
    }

    public function labelText(): string
    {
        return $this->value;
    }

    /**
     * Register button colours, the same as the scouts' register.
     */
    public function buttonClasses(): string
    {
        return match ($this) {
            self::Present => AttendanceStatus::Present->buttonClasses(),
            self::Absent => AttendanceStatus::Absent->buttonClasses(),
            self::Excused => AttendanceStatus::Excused->buttonClasses(),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Absent => 'red',
            self::Excused => 'blue',
        };
    }
}
