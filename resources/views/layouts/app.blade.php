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
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
<div class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-40 border-b border-navy-900 bg-navy-800 text-white dark:bg-navy-950">
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
                <a href="{{ route('dashboard') }}" class="hidden rounded-lg px-3 py-1.5 text-sm hover:bg-navy-700 sm:inline-block">Modules</a>

                <x-theme-switch/>

                {{-- Notifications bell --}}
                <div x-data="{ open: false, expanded: null }" class="relative">
                    <button type="button" x-on:click="open = !open" class="relative rounded-lg p-1.5 hover:bg-navy-700" aria-label="Notifications">
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
                    <button type="button" x-on:click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-navy-700">
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
            <div @class(['rounded-2xl border border-navy-200 bg-navy-50/40 p-3 sm:p-4 dark:border-navy-800 dark:bg-navy-950/40' => $nav]) data-testid="module-shell">
            @if ($nav)
                {{-- Sub-cards of the module: one per page; the open page shows inside the card below. --}}
                <nav aria-label="{{ $module['title'] ?? 'Menu' }} menu" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6" data-testid="menu-cards">
                    @foreach ($nav as $item)
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @class([
                            'group flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm font-medium shadow-sm transition',
                            'border-navy-500 bg-navy-50 text-navy-900 ring-1 ring-navy-500 dark:border-navy-400 dark:bg-navy-900/50 dark:text-navy-100 dark:ring-navy-400' => $item['active'],
                            'border-gray-200 bg-white text-gray-700 hover:border-navy-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-navy-500' => ! $item['active'],
                        ])>
                            <span @class([
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                'bg-navy-600 text-white' => $item['active'],
                                'bg-navy-100 text-navy-700 group-hover:bg-navy-700 group-hover:text-white dark:bg-navy-900/60 dark:text-navy-200' => ! $item['active'],
                            ])>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path($personal ? 'id' : ($module['icon'] ?? null)) }}"/></svg>
                            </span>
                            <span class="min-w-0 leading-tight">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif
            <div @class(['mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-900' => $nav]) data-testid="page-card">
            <x-flash/>
            <div x-data="scoutPushBanner()" x-show="show" x-cloak class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-navy-200 bg-navy-50 px-4 py-3 text-sm text-navy-900 dark:border-navy-800 dark:bg-navy-900/40 dark:text-navy-100" data-testid="push-banner">
                <span class="min-w-0 flex-1">Turn on notifications to get alerts from the portal on this device, even when the app is closed.</span>
                <button type="button" class="btn-primary btn-sm" x-on:click="turnOn()" x-bind:disabled="busy">Turn on</button>
                <button type="button" class="btn-secondary btn-sm" x-on:click="later()">Not now</button>
            </div>
            {{ $slot }}
            </div>
            </div>
        </main>
    </div>

    <footer class="border-t border-gray-200 py-4 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
        {{ app(\App\Services\SettingsService::class)->footerText() }}
    </footer>
</div>
@livewireScripts
@stack('scripts')
</body>
</html>
