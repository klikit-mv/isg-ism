<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title ?? null])
</head>
<body class="min-h-screen bg-gradient-to-br from-navy-900 via-navy-800 to-navy-950 font-sans text-gray-900 antialiased dark:text-gray-100">
<div class="flex min-h-screen flex-col items-center px-4 py-8 sm:justify-center">
    <a href="{{ route('login') }}" class="mb-6 flex flex-col items-center gap-2 text-white">
        <x-logo class="h-16 w-16"/>
        <span class="text-lg font-semibold">{{ config('scout.name') }}</span>
        <span class="text-sm text-navy-200">{{ config('scout.short_name') }}</span>
    </a>

    <div class="w-full {{ ($width ?? 'md') === 'lg' ? 'max-w-3xl' : 'max-w-md' }} rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800 sm:p-8">
        <x-flash/>
        {{ $slot }}
    </div>

    <div class="mt-6 flex flex-col items-center gap-3 text-xs text-navy-200">
        <x-theme-toggle/>
        <span>{{ app(\App\Services\SettingsService::class)->footerText() }}</span>
    </div>
</div>
@livewireScripts
</body>
</html>
