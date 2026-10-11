<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Year filters open on the current year (Maldives time). Choosing "Any year" sends an empty year, which is kept.
 */
final class YearFilter
{
    /**
     * @param  array<int|string, mixed>  $availableYears  the years the list offers
     */
    public static function applyDefault(Request $request, array $availableYears): void
    {
        $current = scout_now()->format('Y');

        if ($request->has('year') || ! in_array($current, array_map('strval', array_keys($availableYears)), true)) {
            return;
        }

        $request->merge(['year' => $current]);
    }
}
