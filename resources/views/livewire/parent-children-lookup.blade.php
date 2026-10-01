<div>
    <label for="child_lookup" class="label">Your children’s National IDs</label>
    <input id="child_lookup" type="text" wire:model.live.debounce.400ms="query" class="input uppercase" placeholder="Type a child’s National ID" autocomplete="off"
        wire:keydown.enter.prevent="lookup">
    <div wire:loading wire:target="query" class="mt-1 text-xs text-gray-500">Looking up…</div>
    @if ($message)
        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400" data-testid="lookup-message">{{ $message }}</p>
    @endif

    <div class="mt-3 flex flex-wrap gap-2">
        @foreach ($children as $child)
            <span wire:key="child-{{ $child['national_id'] }}" class="inline-flex items-center gap-2 rounded-full bg-gold-100 px-3 py-1 text-sm text-gold-800 dark:bg-gold-900/40 dark:text-gold-200">
                <input type="hidden" name="children[]" value="{{ $child['national_id'] }}">
                {{ $child['name'] }} <span class="text-xs opacity-70">{{ $child['section'] }}</span>
                <button type="button" wire:click="remove('{{ $child['national_id'] }}')" class="font-bold opacity-60 hover:opacity-100" aria-label="Remove">&times;</button>
            </span>
        @endforeach
    </div>
    @error('children')
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
