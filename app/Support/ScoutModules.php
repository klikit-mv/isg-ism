<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Static module definitions for the hub, sidebar and route guard.
 */
final class ScoutModules
{
    /**
     * Routes any signed-in user may open whatever module they are in.
     */
    public const ALWAYS_OPEN = [
        'dashboard', 'modules.enter', 'profile.*', 'password.*', 'logout', 'notifications.*',
        'certificates.verify', 'certificates.verify.*', 'photos.show', 'livewire.*',
    ];

    /**
     * @return array<string, array{key: string, title: string, description: string, icon: string, home: string, roles: list<Role>, routes: list<string>, nav: list<array{label: string, route: string, roles?: list<Role>, permission?: Permission, patterns?: list<string>}>}>
     */
    public static function catalog(): array
    {
        return [
            'operations' => [
                'key' => 'operations',
                'title' => 'Scout operations',
                'description' => 'Scouts, groups, activities and attendance.',
                'icon' => 'users',
                'home' => 'students.index',
                'roles' => [Role::Admin, Role::Leader],
                'routes' => ['students.*', 'promotion.*', 'parent-registrations.*', 'groups.*', 'activities.*', 'attendance.*', 'rover-attendance.*'],
                'nav' => [
                    ['label' => 'Students', 'route' => 'students.index', 'patterns' => ['students.*']],
                    ['label' => 'Section promotion', 'route' => 'promotion.index', 'roles' => [Role::Admin], 'patterns' => ['promotion.*']],
                    ['label' => 'Parent registrations', 'route' => 'parent-registrations.index', 'patterns' => ['parent-registrations.*']],
                    ['label' => 'Groups', 'route' => 'groups.index', 'patterns' => ['groups.*']],
                    ['label' => 'Activities', 'route' => 'activities.index', 'patterns' => ['activities.*']],
                    ['label' => 'Mark attendance', 'route' => 'attendance.index', 'patterns' => ['attendance.*']],
                    ['label' => 'Rover attendance', 'route' => 'rover-attendance.index', 'patterns' => ['rover-attendance.*']],
                ],
            ],
            'family' => [
                'key' => 'family',
                'title' => 'Family',
                'description' => 'Your children’s records and attendance.',
                'icon' => 'home',
                'home' => 'family.index',
                'roles' => [Role::Parent],
                'routes' => ['family.*'],
                'nav' => [
                    ['label' => 'My students', 'route' => 'family.index', 'patterns' => ['family.index', 'family.show', 'family.student.*']],
                    ['label' => 'Attendance', 'route' => 'family.attendance', 'patterns' => ['family.attendance']],
                ],
            ],
            'self' => [
                'key' => 'self',
                'title' => 'My record',
                'description' => 'Your details, attendance and fees.',
                'icon' => 'id',
                'home' => 'self.show',
                'roles' => [Role::Student],
                'routes' => ['self.*'],
                'nav' => [
                    ['label' => 'My details', 'route' => 'self.show', 'patterns' => ['self.show', 'self.certificates', 'self.badge-requests', 'self.leadership']],
                    ['label' => 'My attendance', 'route' => 'self.attendance', 'patterns' => ['self.attendance']],
                    ['label' => 'My fees', 'route' => 'self.fees', 'patterns' => ['self.fees']],
                ],
            ],
            'certificates' => [
                'key' => 'certificates',
                'title' => 'Certificates',
                'description' => 'Certificates, badges and leadership records.',
                'icon' => 'award',
                'home' => 'certificates.index',
                'roles' => [Role::Admin, Role::Leader, Role::Parent, Role::Student],
                'routes' => ['certificates.*', 'leadership.*', 'badge-requests.*', 'badges.*', 'certificate-templates.*'],
                'nav' => [
                    ['label' => 'Certificates', 'route' => 'certificates.index', 'patterns' => ['certificates.index', 'certificates.show', 'certificates.create', 'certificates.bulk-create', 'certificates.preview']],
                    ['label' => 'Leadership', 'route' => 'leadership.index', 'patterns' => ['leadership.*']],
                    ['label' => 'Badge requests', 'route' => 'badge-requests.index', 'patterns' => ['badge-requests.*']],
                    ['label' => 'Badges', 'route' => 'badges.index', 'roles' => [Role::Admin, Role::Leader], 'patterns' => ['badges.*']],
                    ['label' => 'Templates', 'route' => 'certificate-templates.index', 'roles' => [Role::Admin], 'patterns' => ['certificate-templates.*']],
                    ['label' => 'Verify certificate', 'route' => 'certificates.verify', 'patterns' => ['certificates.verify', 'certificates.verify.*']],
                ],
            ],
            'finance' => [
                'key' => 'finance',
                'title' => 'Fees and payments',
                'description' => 'Class fees, annual fees and payments.',
                'icon' => 'wallet',
                'home' => 'class-fees.index',
                'roles' => [Role::Admin, Role::Leader, Role::Parent, Role::Student],
                'routes' => ['class-fees.*', 'annual-fees.*', 'payments.*', 'payment-verification.*'],
                'nav' => [
                    ['label' => 'Class fees', 'route' => 'class-fees.index', 'patterns' => ['class-fees.*']],
                    ['label' => 'Annual fees', 'route' => 'annual-fees.index', 'patterns' => ['annual-fees.*']],
                    ['label' => 'Payments', 'route' => 'payments.index', 'patterns' => ['payments.*']],
                    ['label' => 'Verification', 'route' => 'payment-verification.index', 'permission' => Permission::VerifyPayments, 'patterns' => ['payment-verification.*']],
                ],
            ],
            'shop' => [
                'key' => 'shop',
                'title' => 'Scout shop',
                'description' => 'Uniforms, badges and supplies.',
                'icon' => 'bag',
                'home' => 'shop.index',
                'roles' => [Role::Admin, Role::Leader, Role::Parent, Role::Student],
                'routes' => ['shop.*', 'purchases.*'],
                'nav' => [
                    ['label' => 'Shop', 'route' => 'shop.index', 'patterns' => ['shop.*']],
                    ['label' => 'Purchases', 'route' => 'purchases.index', 'patterns' => ['purchases.*']],
                ],
            ],
            'reports' => [
                'key' => 'reports',
                'title' => 'Reports',
                'description' => 'Attendance, fees, payments and shop reports.',
                'icon' => 'chart',
                'home' => 'reports.index',
                'roles' => [Role::Admin, Role::Leader],
                'routes' => ['reports.*'],
                'nav' => [
                    ['label' => 'Reports', 'route' => 'reports.index', 'patterns' => ['reports.*']],
                ],
            ],
            'administration' => [
                'key' => 'administration',
                'title' => 'Administration',
                'description' => 'Users, parent links, settings, audit and import.',
                'icon' => 'cog',
                'home' => 'users.index',
                'roles' => [Role::Admin],
                'routes' => ['users.*', 'parent-links.*', 'settings.*', 'audit-logs.*', 'import.*'],
                'nav' => [
                    ['label' => 'Users', 'route' => 'users.index', 'patterns' => ['users.*']],
                    ['label' => 'Parent links', 'route' => 'parent-links.index', 'patterns' => ['parent-links.*']],
                    ['label' => 'Settings', 'route' => 'settings.index', 'patterns' => ['settings.*']],
                    ['label' => 'Audit logs', 'route' => 'audit-logs.index', 'patterns' => ['audit-logs.*']],
                    ['label' => 'Import', 'route' => 'import.index', 'patterns' => ['import.*']],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return self::catalog()[$key] ?? null;
    }

    public static function canOpen(User $user, string $key): bool
    {
        $module = self::find($key);

        if ($module === null || ! $user->isActive()) {
            return false;
        }

        return $user->hasAnyRole($module['roles']);
    }

    /**
     * Modules the user may open, for the hub.
     *
     * @return list<array<string, mixed>>
     */
    public static function cards(User $user): array
    {
        return array_values(array_filter(self::catalog(), fn (array $module) => self::canOpen($user, $module['key'])));
    }

    /**
     * @return list<string>
     */
    public static function modulesForRoute(?string $routeName): array
    {
        if ($routeName === null) {
            return [];
        }

        $keys = [];

        foreach (self::catalog() as $key => $module) {
            if (self::routeMatches($routeName, $module['routes'])) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function isAlwaysOpen(?string $routeName): bool
    {
        return $routeName === null || self::routeMatches($routeName, self::ALWAYS_OPEN);
    }

    /**
     * @param  list<string>  $patterns
     */
    public static function routeMatches(string $routeName, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
