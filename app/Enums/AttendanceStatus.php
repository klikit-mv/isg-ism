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

    /**
     * Register button colours: each status has its own colour, strongest when chosen.
     */
    public function buttonClasses(): string
    {
        return match ($this) {
            self::Present => 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 peer-checked:border-emerald-600 peer-checked:bg-emerald-600 peer-checked:text-white dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-900/30 dark:peer-checked:border-emerald-500 dark:peer-checked:bg-emerald-600 dark:peer-checked:text-white',
            self::Late => 'border-amber-300 text-amber-700 hover:bg-amber-50 peer-checked:border-amber-500 peer-checked:bg-amber-500 peer-checked:text-white dark:border-amber-800 dark:text-amber-300 dark:hover:bg-amber-900/30 dark:peer-checked:border-amber-500 dark:peer-checked:bg-amber-500 dark:peer-checked:text-white',
            self::Absent => 'border-rose-300 text-rose-700 hover:bg-rose-50 peer-checked:border-rose-600 peer-checked:bg-rose-600 peer-checked:text-white dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-900/30 dark:peer-checked:border-rose-500 dark:peer-checked:bg-rose-600 dark:peer-checked:text-white',
            self::Excused => 'border-sky-300 text-sky-700 hover:bg-sky-50 peer-checked:border-sky-600 peer-checked:bg-sky-600 peer-checked:text-white dark:border-sky-800 dark:text-sky-300 dark:hover:bg-sky-900/30 dark:peer-checked:border-sky-500 dark:peer-checked:bg-sky-600 dark:peer-checked:text-white',
        };
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
