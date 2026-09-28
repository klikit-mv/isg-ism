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
<div x-data="{ sidebar: false }" class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-40 border-b border-navy-900 bg-navy-800 text-white dark:bg-navy-950">
        <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4">
            @if ($nav)
                <button type="button" class="rounded p-1 hover:bg-navy-700 md:hidden" x-on:click="sidebar = !sidebar" aria-label="Menu">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            @endif
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
                        <div class="px-4 py-2"><x-theme-toggle/></div>
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
        @if ($nav)
            <aside class="fixed inset-y-14 left-0 z-30 w-60 -translate-x-full overflow-y-auto border-r border-gray-200 bg-white p-3 transition md:static md:inset-auto md:translate-x-0 md:bg-transparent dark:border-gray-800 dark:bg-gray-900 md:dark:bg-transparent"
                :class="sidebar ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">
                @if ($module)
                    <div class="mb-2 px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $module['title'] }}</div>
                @endif
                <nav class="space-y-1">
                    @foreach ($nav as $item)
                        <a href="{{ $item['url'] }}" @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium',
                            'bg-navy-100 text-navy-800 dark:bg-navy-900/60 dark:text-navy-100' => $item['active'],
                            'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => ! $item['active'],
                        ])>{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            </aside>
            <div x-show="sidebar" x-cloak x-on:click="sidebar = false" class="fixed inset-0 top-14 z-20 bg-black/30 md:hidden"></div>
        @endif

        <main class="min-w-0 flex-1 px-4 py-6 sm:px-6">
            <x-flash/>
            {{ $slot }}
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
