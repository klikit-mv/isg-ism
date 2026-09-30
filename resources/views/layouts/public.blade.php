<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title ?? null])
</head>
<body class="flex min-h-screen flex-col bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
<header class="bg-navy-800 text-white shadow">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <x-logo class="h-10 w-10"/>
            <span class="hidden flex-col leading-tight sm:flex">
                <span class="font-semibold">{{ config('scout.name') }}</span>
                <span class="text-xs text-navy-200">{{ config('scout.short_name') }}</span>
            </span>
        </a>
        <div class="flex items-center gap-2">
            <x-theme-switch/>
            @auth
                <a href="{{ route('dashboard') }}" class="btn-accent btn-sm">Go to portal</a>
            @else
                <a href="{{ route('register') }}" class="hidden rounded-lg px-3 py-1.5 text-sm font-medium text-navy-100 hover:bg-navy-700 sm:inline-block">Join</a>
                <a href="{{ route('login') }}" class="btn-accent btn-sm">Sign in</a>
            @endauth
        </div>
    </div>
</header>

<main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
    <x-flash/>
    {{ $slot }}
</main>

<footer class="border-t border-gray-200 py-6 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
    {{ app(\App\Services\SettingsService::class)->footerText() }}
</footer>
@livewireScripts
</body>
</html>
