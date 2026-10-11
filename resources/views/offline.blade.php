<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline · {{ config('scout.short_name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #1e3a8a; color: #fff; text-align: center; padding: 1.5rem; }
        button { margin-top: 1rem; padding: .6rem 1.2rem; border: 0; border-radius: .5rem; background: #fbbf24; color: #1e3a8a; font-weight: 600; }
    </style>
</head>
<body>
    <div>
        <h1>You are offline</h1>
        <p>{{ config('scout.name') }} needs an internet connection. Check your connection and try again.</p>
        <button type="button" onclick="location.reload()">Try again</button>
    </div>
</body>
</html>
