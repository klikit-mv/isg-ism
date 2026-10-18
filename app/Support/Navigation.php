<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class Navigation
{
    /**
     * Routes that belong to the signed-in person rather than to a module.
     */
    public const PERSONAL_ROUTES = ['profile.*', 'notifications.*'];

    public static function isPersonal(?string $routeName): bool
    {
        return $routeName !== null && ScoutModules::routeMatches($routeName, self::PERSONAL_ROUTES);
    }

    /**
     * Sidebar for the personal area: only the user's own pages.
     *
     * @return list<array{label: string, url: string, active: bool}>
     */
    public static function personal(): array
    {
        $current = Route::currentRouteName() ?? '';

        return [
            ['label' => 'My profile', 'url' => route('profile.edit'), 'active' => str_starts_with($current, 'profile.')],
            ['label' => 'Notifications', 'url' => route('notifications.index'), 'active' => str_starts_with($current, 'notifications.')],
        ];
    }

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
