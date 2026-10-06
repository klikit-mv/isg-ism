<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@if ($faviconUrl = app(\App\Services\SettingsService::class)->logoUrl())
    <link rel="icon" href="{{ $faviconUrl }}" sizes="any">
    <link rel="shortcut icon" href="{{ $faviconUrl }}">
@endif
<link rel="manifest" href="{{ route('pwa.manifest', [], false) }}">
<link rel="apple-touch-icon" href="{{ route('pwa.icon', ['size' => 180], false) }}">
<meta name="theme-color" content="#1e3a8a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ \Illuminate\Support\Str::limit(config('scout.short_name'), 12, '') }}">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
    }
</script>
<title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ config('scout.short_name') }}</title>
<script>
    (function () {
        var mode = 'system';
        try { mode = localStorage.getItem('scout-theme') || 'system'; } catch (e) {}
        var dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (dark) { document.documentElement.classList.add('dark'); }
    })();
</script>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
