<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('scout.short_name') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #3b0764; color: #f5f3ff; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box; }
        .box { max-width: 28rem; text-align: center; }
        .code { font-size: 3rem; font-weight: 800; color: #10b981; margin: 0; }
        h1 { font-size: 1.5rem; margin: .5rem 0; }
        p { color: #e9d5ff; line-height: 1.5; }
        a { display: inline-block; margin-top: 1rem; padding: .6rem 1.2rem; background: #10b981; color: #fff; border-radius: .5rem; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="box">
    <p class="code">{{ $code }}</p>
    <h1>{{ $title }}</h1>
    <p>{{ ($code === '403' && isset($exception) && $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.') ? $exception->getMessage() : $message }}</p>
    <a href="{{ url('/') }}">Go to the portal</a>
</div>
</body>
</html>
