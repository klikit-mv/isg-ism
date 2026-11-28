{{-- Searchable multi-select pick list. $options: list of ['id' => .., 'label' => .., 'hint' => ..] --}}
@props(['name', 'label' => null, 'options' => [], 'selected' => [], 'placeholder' => 'Search…'])

@php
    $selectedIds = array_map('strval', old(rtrim($name, '[]'), $selected));
    $payload = array_map(fn ($o) => ['id' => (string) $o['id'], 'label' => $o['label'], 'hint' => $o['hint'] ?? ''], $options);
@endphp

<div x-data="{
        options: @js($payload),
        selected: @js($selectedIds),
        search: '',
        get filtered() {
            const term = this.search.toLowerCase();
            return this.options.filter(o => ! term || (o.label + ' ' + o.hint).toLowerCase().includes(term)).slice(0, 50);
        },
        toggle(id) { this.selected.includes(id) ? this.selected = this.selected.filter(s => s !== id) : this.selected.push(id); },
        labelFor(id) { return (this.options.find(o => o.id === id) || {}).label || id; },
    }">
    @if ($label)
        <span class="label">{{ $label }} <span class="font-normal text-gray-400" x-text="'(' + selected.length + ')'"></span></span>
    @endif
    <template x-for="id in selected" :key="id">
        <input type="hidden" name="{{ $name }}" :value="id">
    </template>
    <div class="mb-2 flex flex-wrap gap-1">
        <template x-for="id in selected" :key="'chip' + id">
            <span class="inline-flex items-center gap-1 rounded-full bg-navy-100 px-2.5 py-0.5 text-xs text-navy-800 dark:bg-navy-900 dark:text-navy-100">
                <span x-text="labelFor(id)"></span>
                <button type="button" x-on:click="toggle(id)" class="font-bold opacity-60 hover:opacity-100" aria-label="Remove">&times;</button>
            </span>
        </template>
    </div>
    <input type="search" x-model="search" class="input" placeholder="{{ $placeholder }}">
    <div class="mt-1 max-h-56 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <template x-for="option in filtered" :key="option.id">
            <label class="flex cursor-pointer items-center gap-2 border-b border-gray-100 px-3 py-2 text-sm last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
                <input type="checkbox" class="rounded border-gray-300 text-navy-600" :checked="selected.includes(option.id)" x-on:change="toggle(option.id)">
                <span x-text="option.label"></span>
                <span class="ml-auto text-xs text-gray-400" x-text="option.hint"></span>
            </label>
        </template>
        <div x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-500">No matches.</div>
    </div>
    @error(rtrim($name, '[]'))
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
