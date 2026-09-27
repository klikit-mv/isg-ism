<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class Navigation
{
    /**
     * Sidebar items for the current module, filtered by role and permission.
     *
     * @return list<array{label: string, url: string, active: bool}>
     */
    public static function for(User $user, ?string $moduleKey): array
    {
        $module = $moduleKey ? ScoutModules::find($moduleKey) : null;

        if ($module === null || ! ScoutModules::canOpen($user, $moduleKey)) {
            return [];
        }

        $current = Route::currentRouteName() ?? '';
        $items = [];

        foreach ($module['nav'] as $item) {
            if (isset($item['roles']) && ! $user->hasAnyRole($item['roles'])) {
                continue;
            }

            if (isset($item['permission']) && ! $user->hasPermission($item['permission'])) {
                continue;
            }

            if (! Route::has($item['route'])) {
                continue;
            }

            $items[] = [
                'label' => $item['label'],
                'url' => route($item['route']),
                'active' => ScoutModules::routeMatches($current, $item['patterns'] ?? [$item['route']]),
            ];
        }

        return $items;
    }
}
