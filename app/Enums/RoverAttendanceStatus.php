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

    public function tone(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Absent => 'red',
            self::Excused => 'blue',
        };
    }
}
