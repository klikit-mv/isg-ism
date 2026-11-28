@props(['name', 'label' => null, 'value' => null, 'rows' => 3])

<div>
    @if ($label)
        <label for="{{ $name }}" class="label">{{ $label }}</label>
    @endif
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->merge(['class' => 'input']) }}>{{ old($name, $value) }}</textarea>
    @error($name)
        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror
</div>
