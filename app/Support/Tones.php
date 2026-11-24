<?php

namespace App\Support;

/**
 * Card colours per module. Full class names so Tailwind can see them.
 */
final class Tones
{
    private const MAP = [
        'operations' => 'bg-violet-600 hover:bg-violet-700 border-violet-700 dark:bg-violet-700 dark:hover:bg-violet-600',
        'family' => 'bg-sky-600 hover:bg-sky-700 border-sky-700 dark:bg-sky-700 dark:hover:bg-sky-600',
        'self' => 'bg-teal-600 hover:bg-teal-700 border-teal-700 dark:bg-teal-700 dark:hover:bg-teal-600',
        'certificates' => 'bg-amber-500 hover:bg-amber-600 border-amber-600 dark:bg-amber-600 dark:hover:bg-amber-500',
        'finance' => 'bg-emerald-600 hover:bg-emerald-700 border-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600',
        'bank' => 'bg-indigo-600 hover:bg-indigo-700 border-indigo-700 dark:bg-indigo-700 dark:hover:bg-indigo-600',
        'shop' => 'bg-rose-500 hover:bg-rose-600 border-rose-600 dark:bg-rose-600 dark:hover:bg-rose-500',
        'events' => 'bg-orange-500 hover:bg-orange-600 border-orange-600 dark:bg-orange-600 dark:hover:bg-orange-500',
        'reports' => 'bg-cyan-600 hover:bg-cyan-700 border-cyan-700 dark:bg-cyan-700 dark:hover:bg-cyan-600',
        'administration' => 'bg-slate-600 hover:bg-slate-700 border-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600',
    ];

    public static function for(?string $key): string
    {
        return self::MAP[$key] ?? self::MAP['operations'];
    }
}
