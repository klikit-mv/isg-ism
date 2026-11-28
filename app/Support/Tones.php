<?php

namespace App\Support;

/**
 * Card colours per module. Full class names so Tailwind can see them.
 */
final class Tones
{
    private const MAP = [
        'operations' => 'bg-violet-100 hover:bg-violet-200 border-violet-200 text-violet-950 dark:bg-violet-950/60 dark:hover:bg-violet-900/70 dark:border-violet-900 dark:text-violet-50',
        'family' => 'bg-sky-100 hover:bg-sky-200 border-sky-200 text-sky-950 dark:bg-sky-950/60 dark:hover:bg-sky-900/70 dark:border-sky-900 dark:text-sky-50',
        'self' => 'bg-teal-100 hover:bg-teal-200 border-teal-200 text-teal-950 dark:bg-teal-950/60 dark:hover:bg-teal-900/70 dark:border-teal-900 dark:text-teal-50',
        'certificates' => 'bg-amber-100 hover:bg-amber-200 border-amber-200 text-amber-950 dark:bg-amber-950/60 dark:hover:bg-amber-900/70 dark:border-amber-900 dark:text-amber-50',
        'finance' => 'bg-emerald-100 hover:bg-emerald-200 border-emerald-200 text-emerald-950 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/70 dark:border-emerald-900 dark:text-emerald-50',
        'bank' => 'bg-indigo-100 hover:bg-indigo-200 border-indigo-200 text-indigo-950 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/70 dark:border-indigo-900 dark:text-indigo-50',
        'shop' => 'bg-rose-100 hover:bg-rose-200 border-rose-200 text-rose-950 dark:bg-rose-950/60 dark:hover:bg-rose-900/70 dark:border-rose-900 dark:text-rose-50',
        'events' => 'bg-orange-100 hover:bg-orange-200 border-orange-200 text-orange-950 dark:bg-orange-950/60 dark:hover:bg-orange-900/70 dark:border-orange-900 dark:text-orange-50',
        'reports' => 'bg-cyan-100 hover:bg-cyan-200 border-cyan-200 text-cyan-950 dark:bg-cyan-950/60 dark:hover:bg-cyan-900/70 dark:border-cyan-900 dark:text-cyan-50',
        'administration' => 'bg-slate-100 hover:bg-slate-200 border-slate-200 text-slate-950 dark:bg-slate-950/60 dark:hover:bg-slate-900/70 dark:border-slate-900 dark:text-slate-50',
    ];

    public static function for(?string $key): string
    {
        return self::MAP[$key] ?? self::MAP['operations'];
    }
}
