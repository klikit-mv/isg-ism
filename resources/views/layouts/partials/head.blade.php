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
<script>
    // If the stylesheet or script cannot be loaded (a bad copy kept by the browser or the app cache, a dropped connection),
    // clear what this device kept and reload once; if it still fails, say so instead of showing a broken page.
    window.scoutAssetsFailed = function () {
        var retried = false;
        try { retried = sessionStorage.getItem('scout-asset-retry') === '1'; sessionStorage.setItem('scout-asset-retry', '1'); } catch (e) {}

        if (retried) {
            document.addEventListener('DOMContentLoaded', function () {
                var bar = document.createElement('div');
                bar.setAttribute('role', 'alert');
                bar.style.cssText = 'padding:12px 16px;background:#fef3c7;color:#78350f;font:14px/1.4 system-ui,sans-serif;text-align:center';
                bar.innerHTML = 'The page design could not be loaded. Check your connection, then <a href="" style="color:#78350f;font-weight:600" onclick="location.reload(true);return false">reload the page</a>.';
                document.body.insertBefore(bar, document.body.firstChild);
            });

            return;
        }

        var jobs = [];
        if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) {
            jobs.push(navigator.serviceWorker.getRegistrations().then(function (list) { return Promise.all(list.map(function (r) { return r.unregister(); })); }));
        }
        if (window.caches && caches.keys) {
            jobs.push(caches.keys().then(function (keys) { return Promise.all(keys.map(function (k) { return caches.delete(k); })); }));
        }
        var again = function () { location.reload(); };
        Promise.all(jobs).then(again, again);
    };
    window.addEventListener('load', function () {
        try { if (! window.scoutAssetsBroken) { sessionStorage.removeItem('scout-asset-retry'); } } catch (e) {}
    });
</script>
{{-- The font loads without holding the page back: if the font host is slow or blocked, the system font is used. --}}
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap"></noscript>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@include('layouts.partials.push-script')
@livewireStyles
