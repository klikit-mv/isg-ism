@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'help' => null, 'searchAfter' => 8])

@php
    $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name));
    $selected = old(str_replace(['[', ']'], ['.', ''], $name), $value instanceof \BackedEnum ? $value->value : $value);
    $selected = is_array($selected) ? array_map('strval', $selected) : (string) $selected;
    $searchable = ! $attributes->has('multiple') && count($options) > $searchAfter;
    $choices = collect($options)->map(fn ($optionLabel, $optionValue) => ['v' => (string) $optionValue, 'l' => (string) $optionLabel])->values()->all();
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}</label>
    @endif

    @if ($searchable)
        {{-- Many options: a dropdown with a search box. The arrow marks it as a dropdown. --}}
        <div
            x-data="{
                open: false, q: '', value: @js($selected), choices: @js($choices), placeholder: @js($placeholder ?? 'Choose…'),
                get label() { const hit = this.choices.find((c) => c.v === this.value); return hit ? hit.l : ''; },
                get filtered() { const q = this.q.trim().toLowerCase(); return q === '' ? this.choices : this.choices.filter((c) => c.l.toLowerCase().includes(q)); },
                toggle() { this.open = ! this.open; this.q = ''; if (this.open) this.$nextTick(() => this.$refs.search.focus()); },
                choose(v) { this.value = v; this.open = false; this.q = ''; },
            }"
            x-on:keydown.escape.stop="open = false"
            x-on:click.outside="open = false"
            class="relative"
        >
            <input type="hidden" name="{{ $name }}" x-bind:value="value">
            <div class="relative">
                <input type="text" id="{{ $id }}" readonly x-on:click="toggle()" x-on:keydown.enter.prevent="toggle()" x-on:keydown.down.prevent="open = true"
                    x-bind:value="label" x-bind:placeholder="placeholder" aria-haspopup="listbox" x-bind:aria-expanded="open" autocomplete="off"
                    {{ $attributes->only(['data-testid'])->merge(['class' => 'input cursor-pointer pr-9']) }}>
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div x-show="open" x-cloak x-transition.opacity class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-100 p-2 dark:border-gray-700">
                    <input type="search" x-ref="search" x-model="q" placeholder="Type to search… ({{ count($choices) }} options)" class="input !py-1.5 text-sm" autocomplete="off"
                        x-on:keydown.enter.prevent="filtered.length && choose(filtered[0].v)">
                </div>
                <ul class="max-h-60 overflow-y-auto py-1 text-sm" role="listbox">
                    @if ($placeholder !== null)
                        <li><button type="button" class="block w-full px-3 py-1.5 text-left text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700" x-on:click="choose('')">{{ $placeholder }}</button></li>
                    @endif
                    <template x-for="choice in filtered" x-bind:key="choice.v">
                        <li><button type="button" role="option" class="block w-full px-3 py-1.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700"
                            x-bind:class="choice.v === value ? 'bg-navy-50 font-medium text-navy-800 dark:bg-navy-900/40 dark:text-navy-200' : ''"
                            x-on:click="choose(choice.v)" x-text="choice.l"></button></li>
                    </template>
                    <li x-show="filtered.length === 0" class="px-3 py-2 text-gray-500">No matches</li>
                </ul>
            </div>
        </div>
    @else
        <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('id')->merge(['class' => 'input']) }}>
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected(is_array($selected) ? in_array((string) $optionValue, $selected, true) : (string) $optionValue === $selected)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @endif

    @if ($help)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
    @endif
    @error(str_replace(['[', ']'], ['.', ''], $name))
        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror
</div>
