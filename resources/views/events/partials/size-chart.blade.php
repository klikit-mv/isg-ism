@if ($item->measurements())
    <div class="mt-2 overflow-x-auto" data-testid="size-chart">
        <table class="text-xs">
            <thead><tr class="text-left text-gray-500"><th class="pr-4 font-medium">Size</th><th class="font-medium">Measurements</th></tr></thead>
            <tbody>
                @foreach ($item->sizeList() as $size)
                    <tr><td class="pr-4 font-semibold">{{ $size }}</td><td class="text-gray-600 dark:text-gray-300">{{ $item->measurements()[$size] ?? '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@if ($item->size_guide)
    <p class="mt-1 text-xs italic text-gray-500 dark:text-gray-400">{{ $item->size_guide }}</p>
@endif
