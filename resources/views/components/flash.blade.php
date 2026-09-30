@foreach (['success' => 'emerald', 'status' => 'emerald', 'error' => 'rose', 'warning' => 'amber'] as $key => $color)
    @if (session($key) && ! in_array(session($key), ['pin-updated', 'profile-updated'], true))
        <div x-data="{ open: true }" x-show="open" class="mb-4 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm
            {{ $color === 'emerald' ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200' : '' }}
            {{ $color === 'rose' ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200' : '' }}
            {{ $color === 'amber' ? 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200' : '' }}" role="alert">
            <div>{{ session($key) }}</div>
            <button type="button" x-on:click="open = false" class="opacity-60 hover:opacity-100" aria-label="Dismiss">&times;</button>
        </div>
    @endif
@endforeach
@if ($errors->any() && ! $errors->hasBag('updatePin'))
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200" role="alert">
        <p class="font-medium">Please check the form.</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
