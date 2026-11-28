<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\TelegramService;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'telegramBot' => $telegram->botUsername(),
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

    /**
     * Upload or remove the signed-in user's profile picture.
     */
    public function avatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $old = $user->avatar_path;

        if ($request->boolean('remove')) {
            $user->forceFill(['avatar_path' => null])->save();
            $this->deleteAvatar($old);
            $this->audit->record('profile.avatar_removed', $user);

            return redirect()->route('profile.edit')->with('success', 'Your profile picture was removed.');
        }

        $request->validate(['avatar' => ['required', 'bail', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048']], [
            'avatar.required' => 'Choose a picture to upload.',
        ]);

        $file = $request->file('avatar');

        if (Uploads::imageSize($file) === null) {
            throw ValidationException::withMessages(['avatar' => 'The profile picture must be a PNG, JPEG or WebP image.']);
        }

        $path = Uploads::store($file, 'avatars', $user->uuid.'-'.Str::random(8).'.'.strtolower($file->extension() ?: 'jpg'), 'public');
        $user->forceFill(['avatar_path' => $path])->save();
        $this->deleteAvatar($old);
        $this->audit->record('profile.avatar_updated', $user);

        return redirect()->route('profile.edit')->with('success', 'Your profile picture was updated.');
    }

    private function deleteAvatar(?string $path): void
    {
        if ($path && str_starts_with($path, 'avatars/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
