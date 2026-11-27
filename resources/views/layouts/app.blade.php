@php
    $user = auth()->user();
    $personal = \App\Support\Navigation::isPersonal(\Illuminate\Support\Facades\Route::currentRouteName());
    $moduleKey = $personal ? null : session('current_module');
    $module = $personal ? ['title' => 'My account'] : ($moduleKey ? \App\Support\ScoutModules::find($moduleKey) : null);
    $nav = ! $user ? [] : ($personal ? \App\Support\Navigation::personal() : \App\Support\Navigation::for($user, $moduleKey));
    $unread = $user ? $user->unreadNotifications()->latest()->limit(8)->get() : collect();
    $unreadCount = $user ? $user->unreadNotifications()->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title ?? null])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-gray-900 antialiased dark:bg-slate-950 dark:text-gray-100">
<div class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-40 border-b border-indigo-900/40 bg-gradient-to-r from-indigo-800 via-violet-700 to-fuchsia-700 text-white dark:from-slate-900 dark:via-indigo-950 dark:to-slate-900">
        <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold">
                <x-logo class="h-8 w-8"/>
                <span class="hidden sm:inline">{{ config('scout.short_name') }}</span>
            </a>
            @if ($module)
                <span class="hidden text-navy-300 sm:inline">/</span>
                <span class="truncate text-sm text-navy-100">{{ $module['title'] }}</span>
            @endif

            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('dashboard') }}" class="hidden rounded-lg px-3 py-1.5 text-sm hover:bg-white/15 sm:inline-block">Modules</a>

                <x-theme-switch/>

                {{-- Notifications bell --}}
                <div x-data="{ open: false, expanded: null }" class="relative">
                    <button type="button" x-on:click="open = !open" class="relative rounded-lg p-1.5 hover:bg-white/15" aria-label="Notifications">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                        @if ($unreadCount > 0)
                            <span class="absolute -right-0.5 -top-0.5 rounded-full bg-gold-500 px-1.5 text-[10px] font-bold" data-testid="unread-count">{{ $unreadCount }}</span>
                        @endif
                    </button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute right-0 mt-2 w-80 max-w-[90vw] overflow-hidden rounded-xl bg-white text-gray-800 shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:text-gray-100">
                        <div class="border-b border-gray-100 px-4 py-2 text-sm font-semibold dark:border-gray-700">Notifications</div>
                        <div class="max-h-96 overflow-y-auto">
                            @forelse ($unread as $index => $notification)
                                <div class="border-b border-gray-100 px-4 py-3 text-sm dark:border-gray-700">
                                    <button type="button" class="w-full text-left" x-on:click="expanded = expanded === {{ $index }} ? null : {{ $index }}">
                                        <div class="font-medium">{{ $notification->data['title'] ?? 'Notification' }}</div>
                                        <div class="text-gray-500 dark:text-gray-400" :class="expanded === {{ $index }} ? '' : 'truncate'">{{ $notification->data['body'] ?? '' }}</div>
                                    </button>
                                    <a href="{{ route('notifications.show', $notification->id) }}" x-show="expanded === {{ $index }}" class="link mt-1 inline-block text-xs">Open full details</a>
                                </div>
                            @empty
                                <div class="px-4 py-6 text-center text-sm text-gray-500">You are all caught up.</div>
                            @endforelse
                        </div>
                        <a href="{{ route('notifications.index') }}" class="block bg-gray-50 px-4 py-2 text-center text-sm font-medium text-navy-700 dark:bg-gray-900 dark:text-navy-300">See all notifications</a>
                    </div>
                </div>

                {{-- User menu --}}
                <div x-data="{ open: false }" class="relative">
                    <button type="button" x-on:click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-white/15">
                        <x-avatar :user="$user" class="h-7 w-7 text-xs"/>
                        <span class="hidden max-w-[10rem] truncate md:inline">{{ $user->name }}</span>
                    </button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl bg-white py-1 text-sm text-gray-800 shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:text-gray-100">
                        <div class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">{{ $user->national_id }}</div>
                        <a href="{{ route('dashboard') }}" class="block px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700">Modules</a>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700">Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700">Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="mx-auto flex w-full max-w-7xl flex-1">
        <main class="min-w-0 flex-1 px-4 py-6 sm:px-6">
            @if ($nav && $personal)
                <nav aria-label="My account menu" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" data-testid="menu-cards">
                    @foreach ($nav as $item)
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @class([
                            'rounded-xl border px-4 py-3 text-sm font-medium shadow-sm transition',
                            'border-navy-500 bg-navy-50 text-navy-900 ring-1 ring-navy-500 dark:bg-navy-900/50 dark:text-navy-100' => $item['active'],
                            'border-gray-200 bg-white text-gray-700 hover:border-navy-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200' => ! $item['active'],
                        ])>{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            @elseif (count($nav) > 1 && $moduleKey && \Illuminate\Support\Facades\Route::currentRouteName() !== 'modules.enter')
                <a href="{{ route('modules.enter', $moduleKey) }}" class="mb-4 inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-navy-700 shadow-sm hover:border-navy-300 dark:border-gray-700 dark:bg-gray-800 dark:text-navy-200" data-testid="back-to-menu">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    {{ $module['title'] }}
                </a>
            @endif
            <x-flash/>
            <div x-data="scoutPushBanner()" x-show="show" x-cloak class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-navy-200 bg-navy-50 px-4 py-3 text-sm text-navy-900 dark:border-navy-800 dark:bg-navy-900/40 dark:text-navy-100" data-testid="push-banner">
                <span class="min-w-0 flex-1">Turn on notifications to get alerts from the portal on this device, even when the app is closed.</span>
                <button type="button" class="btn-primary btn-sm" x-on:click="turnOn()" x-bind:disabled="busy">Turn on</button>
                <button type="button" class="btn-secondary btn-sm" x-on:click="later()">Not now</button>
            </div>
            {{ $slot }}
        </main>
    </div>

    <footer class="border-t border-gray-200 py-4 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
        {{ app(\App\Services\SettingsService::class)->footerText() }}
    </footer>
</div>
<div x-data="scoutProgress()" x-on:scout-progress.window="start($event.detail.label, $event.detail.items)" x-on:scout-progress-stop.window="stop()" x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-900/60 px-4" role="alertdialog" aria-modal="true" aria-live="polite" data-testid="progress-modal">
    <div class="w-full max-w-sm rounded-xl bg-white p-6 text-center shadow-xl dark:bg-gray-800">
        <p class="font-semibold text-gray-900 dark:text-gray-100" x-text="label + '…'"></p>
        <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="percent">
            <div class="h-full rounded-full bg-navy-600 transition-all duration-200" x-bind:style="'width:' + percent + '%'"></div>
        </div>
        <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200"><span x-text="percent"></span>%</p>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Please keep this page open.</p>
    </div>
</div>
@livewireScripts
@stack('scripts')
</body>
</html>
