<?php

use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('scout_money')) {
    function scout_money(string|int|float|null $value): string
    {
        return Money::format($value);
    }
}

if (! function_exists('scout_now')) {
    /**
     * Current time in the organisation timezone.
     */
    function scout_now(): Carbon
    {
        return Carbon::now(config('scout.timezone'));
    }
}

if (! function_exists('scout_today')) {
    /**
     * Today's date (Y-m-d) in the organisation timezone. Storage is UTC, so a plain now() is a day behind for the first
     * five hours of every Maldives day.
     */
    function scout_today(): string
    {
        return scout_now()->toDateString();
    }
}

if (! function_exists('scout_date')) {
    function scout_date(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $value instanceof CarbonInterface ? $value->copy() : Carbon::parse($value);

        return $date->setTimezone(config('scout.timezone'))->format('d.m.Y');
    }
}

if (! function_exists('scout_datetime')) {
    function scout_datetime(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $value instanceof CarbonInterface ? $value->copy() : Carbon::parse($value);

        return $date->setTimezone(config('scout.timezone'))->format('d.m.Y H:i');
    }
}

if (! function_exists('scout_long_date')) {
    /**
     * Certificate style date, e.g. "27 September 2026".
     */
    function scout_long_date(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $value instanceof CarbonInterface ? $value->copy() : Carbon::parse($value);

        return $date->setTimezone(config('scout.timezone'))->format('j F Y');
    }
}

if (! function_exists('photo_url')) {
    function photo_url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'drive:')) {
            return route('photos.show', substr($path, 6), false);
        }

        return route('media.show', ['path' => $path], false);
    }
}
