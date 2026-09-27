<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Change the signed-in user's own PIN.
     */
    public function update(Request $request, AuditLogService $audit): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePin', [
            'current_pin' => ['required', 'string'],
            'pin' => ['required', 'string', 'min:4', 'max:32', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_pin'], $user->password)) {
            throw ValidationException::withMessages(['current_pin' => 'Your current PIN is not correct.'])->errorBag('updatePin');
        }

        $user->forceFill([
            'password' => $validated['pin'],
            'legacy_pin_hash' => null,
            'legacy_pin_salt' => null,
        ])->save();

        $audit->record('auth.pin_changed', $user);

        return back()->with('status', 'pin-updated');
    }
}
