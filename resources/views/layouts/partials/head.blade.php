<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@if ($faviconUrl = app(\App\Services\SettingsService::class)->logoUrl())
    <link rel="icon" href="{{ $faviconUrl }}" sizes="any">
    <link rel="shortcut icon" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
@endif
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
