<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\SignatureService;
use App\Services\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    public function edit(Request $request, TelegramService $telegram): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'telegramConfigured' => $telegram->configured(),
            'telegramConnectUrl' => session('telegram_connect_token') ? $telegram->connectUrlFor(session('telegram_connect_token')) : null,
        ]);
    }

    /**
     * Update own name, email and notification choices. Roles cannot change here.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'email_notifications_enabled' => ['sometimes', 'boolean'],
            'telegram_notifications_enabled' => ['sometimes', 'boolean'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'email_notifications_enabled' => $request->boolean('email_notifications_enabled'),
            'telegram_notifications_enabled' => $request->boolean('telegram_notifications_enabled') && filled($user->telegram_chat_id),
        ])->save();

        $this->audit->record('profile.updated', $user);

        return redirect()->route('profile.edit')->with('success', 'Your profile was saved.');
    }

    public function signature(Request $request, SignatureService $signatures): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff(), 403);

        $request->validate(['signature' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:2048']]);
        $signatures->storeUpload($user, $request->file('signature'));
        $this->audit->record('user.signature_uploaded', $user);

        return redirect()->route('profile.edit')->with('success', 'Your signature was uploaded.');
    }
}
