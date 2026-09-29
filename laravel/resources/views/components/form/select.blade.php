@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null])

@php
    $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name));
    $selected = old(str_replace(['[', ']'], ['.', ''], $name), $value instanceof \BackedEnum ? $value->value : $value);
    $selected = is_array($selected) ? array_map('strval', $selected) : (string) $selected;
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}</label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('id')->merge(['class' => 'input']) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(is_array($selected) ? in_array((string) $optionValue, $selected, true) : (string) $optionValue === $selected)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error(str_replace(['[', ']'], ['.', ''], $name))
        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror
</div>
