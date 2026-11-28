<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] }} report · {{ config('scout.organisation') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0; }
        p { margin: 2px 0 12px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f3f0ff; }
        tfoot td { font-weight: bold; background: #f9fafb; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>
    <h1>{{ config('scout.organisation') }} — {{ $meta['title'] }} report</h1>
    <p>Generated {{ scout_datetime(now()) }} by {{ auth()->user()->name }}</p>
    <table>
        <thead><tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>@foreach ($reports->mapRow($type, $row) as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
        <tfoot><tr>@foreach ($reports->totalsRow($type, $totals) as $cell)<td>{{ $cell }}</td>@endforeach</tr></tfoot>
    </table>
</body>
</html>
