<x-app-layout title="My profile">
    <x-page-header title="My profile" description="Your picture, details, notifications and PIN."/>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Profile picture --}}
        <section class="card" data-testid="profile-picture">
            <h2 class="mb-4 text-lg font-semibold">Profile picture</h2>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <x-avatar :user="$user" class="h-24 w-24 text-2xl"/>
                <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="flex-1 space-y-3">
                    @csrf
                    <x-form.file-drop name="avatar" accept="image/png,image/jpeg,image/webp" help="PNG, JPEG or WebP, up to 2 MB. A square picture looks best."/>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn-primary btn-sm">Upload picture</button>
                        @if ($user->avatar_path)
                            <button type="submit" name="remove" value="1" class="btn-secondary btn-sm">Remove picture</button>
                        @endif
                    </div>
                </form>
            </div>
        </section>

        {{-- Details and email notifications --}}
        <section class="card">
            <h2 class="mb-4 text-lg font-semibold">Your details</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <div class="grid gap-4 sm:grid-cols-2">
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

        {{-- Push notifications --}}
        <section class="card" data-testid="push-card">
            <h2 class="mb-2 text-lg font-semibold">Phone and browser notifications</h2>
            <div x-data="scoutPush()" class="space-y-3 text-sm">
                <p class="text-gray-600 dark:text-gray-300">Get alerts on this device even when the portal is closed. Turn it on separately on each phone or computer.</p>
                <p x-show="! supported" x-cloak class="text-amber-700 dark:text-amber-300">This browser cannot show push notifications. On iPhone or iPad, first add the portal to the Home Screen (Share → Add to Home Screen, iOS 16.4 or later) and open it from there.</p>
                <div x-show="supported" x-cloak class="flex flex-wrap gap-2">
                    <button type="button" class="btn-primary btn-sm" x-show="! on" x-on:click="enable()" x-bind:disabled="busy">Turn on notifications</button>
                    <template x-if="on">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-secondary btn-sm" x-on:click="test()">Send me a test</button>
                            <button type="button" class="btn-danger btn-sm" x-on:click="disable()" x-bind:disabled="busy">Turn off</button>
                        </div>
                    </template>
                </div>
                <p class="text-xs text-gray-500" x-show="message" x-text="message" x-cloak></p>
            </div>
        </section>

        {{-- Telegram --}}
        <section class="card" data-testid="telegram-card">
            <h2 class="mb-2 text-lg font-semibold">Telegram notifications</h2>
            @if (! $telegramConfigured)
                <p class="text-sm text-gray-500 dark:text-gray-400">Telegram is not set up for this portal yet. An administrator adds the bot under Administration → Settings.</p>
            @elseif ($user->telegram_chat_id)
                <p class="mb-3 text-sm text-emerald-700 dark:text-emerald-300">
                    Your Telegram is connected{{ $telegramBot ? ' to @'.$telegramBot : '' }}.
                    {{ $user->telegram_notifications_enabled ? 'Notifications are sent there.' : 'Notifications to Telegram are switched off in Your details.' }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('profile.telegram.test') }}">@csrf<button class="btn-secondary btn-sm">Send me a test message</button></form>
                    <form method="POST" action="{{ route('profile.telegram.disconnect') }}">@csrf<button class="btn-danger btn-sm">Disconnect</button></form>
                </div>
            @elseif ($telegramConnectUrl)
                <div x-data="{
                        waiting: true,
                        tries: 0,
                        async check() {
                            if (! this.waiting) return;
                            this.tries++;
                            try {
                                const res = await fetch(@js(route('profile.telegram.confirm')), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                                const data = res.ok ? await res.json() : {};
                                if (data.connected) { this.waiting = false; window.location.reload(); return; }
                            } catch (e) {}
                            if (this.tries < 60) setTimeout(() => this.check(), 5000); else this.waiting = false;
                        },
                    }" x-init="setTimeout(() => check(), 4000)" class="space-y-3 text-sm">
                    <ol class="list-decimal space-y-1 pl-5 text-gray-600 dark:text-gray-300">
                        <li>Press <strong>Open Telegram</strong>. It opens the {{ $telegramBot ? '@'.$telegramBot : '' }} bot.</li>
                        <li>In Telegram, press <strong>Start</strong>.</li>
                        <li>Come back here. This page connects by itself within a few seconds.</li>
                    </ol>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ $telegramConnectUrl }}" target="_blank" rel="noopener" class="btn-accent btn-sm" data-testid="telegram-open">Open Telegram</a>
                        <form method="POST" action="{{ route('profile.telegram.confirm') }}">@csrf<button class="btn-secondary btn-sm">I've pressed Start</button></form>
                    </div>
                    <p class="text-xs text-gray-500" x-show="waiting">Waiting for your Start message… The link works for 30 minutes.</p>
                    <p class="text-xs text-amber-700" x-show="! waiting" x-cloak>Still not connected. Press Start in the bot, then press “I've pressed Start”, or begin again with Connect.</p>
                </div>
            @else
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Get your notifications in Telegram. You only need to do this once.</p>
                <form method="POST" action="{{ route('profile.telegram.connect') }}">@csrf<button class="btn-primary btn-sm">Connect Telegram</button></form>
            @endif
        </section>

        {{-- PIN --}}
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

        {{-- Appearance --}}
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
    </div>
</x-app-layout>
