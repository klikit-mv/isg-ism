<x-app-layout title="Profile">
    <x-page-header title="Profile" description="Your details, notifications and PIN."/>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card">
            <h2 class="mb-4 text-lg font-semibold">Your details</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <span class="label">National ID</span>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $user->national_id }}</p>
                </div>
                <div>
                    <span class="label">Roles</span>
                    <div class="flex flex-wrap gap-1">
                        @forelse ($user->roles() as $role)
                            <x-badge :value="$role"/>
                        @empty
                            <span class="text-sm text-gray-500">No roles</span>
                        @endforelse
                    </div>
                </div>
                <x-form.input name="name" label="Name" :value="$user->name" required/>
                <x-form.input name="email" label="Email" type="email" :value="$user->email"/>
                <div class="space-y-2">
                    <input type="hidden" name="email_notifications_enabled" value="0">
                    <x-form.checkbox name="email_notifications_enabled" label="Email me notifications" :checked="$user->email_notifications_enabled"/>
                    @if ($user->telegram_chat_id)
                        <input type="hidden" name="telegram_notifications_enabled" value="0">
                        <div><x-form.checkbox name="telegram_notifications_enabled" label="Send notifications to Telegram" :checked="$user->telegram_notifications_enabled"/></div>
                    @endif
                </div>
                <button type="submit" class="btn-primary">Save</button>
            </form>
        </section>

        <section class="card">
            <h2 class="mb-4 text-lg font-semibold">Change PIN</h2>
            @if (session('status') === 'pin-updated')
                <p class="mb-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">Your PIN was changed.</p>
            @endif
            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                @foreach (['current_pin' => 'Current PIN', 'pin' => 'New PIN', 'pin_confirmation' => 'Confirm new PIN'] as $field => $label)
                    <div>
                        <label class="label" for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="password" class="input" inputmode="numeric" autocomplete="{{ $field === 'current_pin' ? 'current-password' : 'new-password' }}">
                        @error($field, 'updatePin')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <button type="submit" class="btn-primary">Change PIN</button>
            </form>
        </section>

        <section class="card">
            <h2 class="mb-2 text-lg font-semibold">Appearance</h2>
            <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Choose light or dark mode for this device.</p>
            <x-theme-toggle/>
        </section>

        @if ($user->isStaff())
            <section class="card">
                <h2 class="mb-2 text-lg font-semibold">Signature</h2>
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Used when you sign certificates. PNG or JPEG, up to 2 MB.</p>
                @if ($user->signature_path)
                    <p class="mb-3 text-sm text-emerald-700 dark:text-emerald-300">A signature is on file.</p>
                @endif
                <form method="POST" action="{{ route('profile.signature') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <x-form.file-drop name="signature" accept="image/png,image/jpeg"/>
                    <button type="submit" class="btn-primary">Upload signature</button>
                </form>
            </section>
        @endif

        <section class="card">
            <h2 class="mb-2 text-lg font-semibold">Telegram</h2>
            @if (! $telegramConfigured)
                <p class="text-sm text-gray-500 dark:text-gray-400">Telegram notifications are not set up for this portal yet.</p>
            @elseif ($user->telegram_chat_id)
                <p class="mb-3 text-sm text-emerald-700 dark:text-emerald-300">Your Telegram account is connected.</p>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('profile.telegram.test') }}">@csrf<button class="btn-secondary btn-sm">Send test message</button></form>
                    <form method="POST" action="{{ route('profile.telegram.disconnect') }}">@csrf<button class="btn-danger btn-sm">Disconnect</button></form>
                </div>
            @else
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Get notifications in Telegram. Press Connect, open the bot, press Start, then come back and confirm.</p>
                @if ($telegramConnectUrl)
                    <p class="mb-3 text-sm"><a href="{{ $telegramConnectUrl }}" target="_blank" rel="noopener" class="link">Open the bot in Telegram</a></p>
                    <form method="POST" action="{{ route('profile.telegram.confirm') }}">@csrf<button class="btn-accent btn-sm">I've started the bot</button></form>
                @else
                    <form method="POST" action="{{ route('profile.telegram.connect') }}">@csrf<button class="btn-primary btn-sm">Connect</button></form>
                @endif
            @endif
        </section>
    </div>
</x-app-layout>
