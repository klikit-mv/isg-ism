<?php

namespace App\Http\Middleware;

use App\Support\ScoutModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks routes that belong only to modules the user cannot open and keeps
 * the session's current module in sync with the page being viewed.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();

        if ($user === null || ScoutModules::isAlwaysOpen($routeName)) {
            return $next($request);
        }

        $modules = ScoutModules::modulesForRoute($routeName);

        if ($modules === []) {
            return $next($request);
        }

        $allowed = array_values(array_filter($modules, fn (string $key) => ScoutModules::canOpen($user, $key)));

        abort_if($allowed === [], 403, 'You do not have access to this area.');

        if (! in_array($request->session()->get('current_module'), $allowed, true)) {
            $request->session()->put('current_module', $allowed[0]);
        }

        return $next($request);
    }
}
