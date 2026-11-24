<?php

namespace App\Support;

/**
 * Card colours per module. Full class names so Tailwind can see them.
 */
final class Tones
{
    private const MAP = [
        'operations' => 'bg-violet-700 hover:bg-violet-800 border-violet-800 dark:bg-violet-900 dark:hover:bg-violet-800 dark:border-violet-800',
        'family' => 'bg-sky-700 hover:bg-sky-800 border-sky-800 dark:bg-sky-900 dark:hover:bg-sky-800 dark:border-sky-800',
        'self' => 'bg-teal-700 hover:bg-teal-800 border-teal-800 dark:bg-teal-900 dark:hover:bg-teal-800 dark:border-teal-800',
        'certificates' => 'bg-amber-600 hover:bg-amber-700 border-amber-800 dark:bg-amber-900 dark:hover:bg-amber-800 dark:border-amber-800',
        'finance' => 'bg-emerald-700 hover:bg-emerald-800 border-emerald-800 dark:bg-emerald-900 dark:hover:bg-emerald-800 dark:border-emerald-800',
        'bank' => 'bg-indigo-700 hover:bg-indigo-800 border-indigo-800 dark:bg-indigo-900 dark:hover:bg-indigo-800 dark:border-indigo-800',
        'shop' => 'bg-rose-700 hover:bg-rose-800 border-rose-800 dark:bg-rose-900 dark:hover:bg-rose-800 dark:border-rose-800',
        'events' => 'bg-orange-600 hover:bg-orange-700 border-orange-800 dark:bg-orange-900 dark:hover:bg-orange-800 dark:border-orange-800',
        'reports' => 'bg-cyan-700 hover:bg-cyan-800 border-cyan-800 dark:bg-cyan-900 dark:hover:bg-cyan-800 dark:border-cyan-800',
        'administration' => 'bg-slate-700 hover:bg-slate-800 border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-slate-800',
    ];

    public static function for(?string $key): string
    {
        return self::MAP[$key] ?? self::MAP['operations'];
    }
}
