<?php

namespace App\Http\Controllers;

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

    public function enter(Request $request, string $module): RedirectResponse
    {
        abort_unless(ScoutModules::canOpen($request->user(), $module), 403);

        $request->session()->put('current_module', $module);

        return redirect()->route(ScoutModules::find($module)['home']);
    }
}
