@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null])

@php $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name)); @endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}</label>
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password' && $type !== 'file') value="{{ old(str_replace(['[', ']'], ['.', ''], $name), $value) }}" @endif
        {{ $attributes->except('id')->merge(['class' => $type === 'file' ? 'block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-50 file:px-3 file:py-2 file:text-navy-700 dark:text-gray-300 dark:file:bg-navy-900 dark:file:text-navy-200' : 'input']) }}>
    @if ($help)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
    @endif
    @error(str_replace(['[', ']'], ['.', ''], $name))
        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror
</div>
