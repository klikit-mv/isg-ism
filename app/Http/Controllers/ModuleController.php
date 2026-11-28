<?php

namespace App\Http\Controllers;

use App\Support\Navigation;
use App\Support\ScoutModules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    /**
     * The module hub.
     */
    public function index(Request $request): View
    {
        $request->session()->forget('current_module');

        return view('dashboard', ['modules' => ScoutModules::cards($request->user())]);
    }

    public function enter(Request $request, string $module): RedirectResponse|View
    {
        abort_unless(ScoutModules::canOpen($request->user(), $module), 403);

        $request->session()->put('current_module', $module);

        $definition = ScoutModules::find($module);
        $pages = Navigation::for($request->user(), $module);

        if (count($pages) <= 1) {
            return redirect()->route($definition['home']);
        }

        return view('module', ['module' => $definition, 'pages' => $pages]);
    }
}
