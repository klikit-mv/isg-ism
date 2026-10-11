<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ScoutSection: string
{
    use HasOptions;

    case PreCub = 'Pre Cub';
    case CubScout = 'Cub Scout';
    case Scout = 'Scout';
    case Rover = 'Rover';

    /**
     * The next section on the growth path, or null for Rovers.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::PreCub => self::CubScout,
            self::CubScout => self::Scout,
            self::Scout => self::Rover,
            self::Rover => null,
        };
    }

    public function canGraduate(): bool
    {
        return $this->next() !== null;
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::PreCub => 'FLHSG-PC',
            self::CubScout => 'FLHSG-PA',
            self::Scout => 'FLHSG-PB',
            self::Rover => 'FLHSG-PD',
        };
    }

    public function labelText(): string
    {
        return $this->value;
    }

    public function tone(): string
    {
        return match ($this) {
            self::PreCub => 'amber',
            self::CubScout => 'blue',
            self::Scout => 'green',
            self::Rover => 'purple',
        };
    }
}
