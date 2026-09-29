@props(['name', 'label', 'checked' => false, 'value' => '1'])

<label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name) !== null ? (bool) old($name) : $checked)
        {{ $attributes->merge(['class' => 'rounded border-gray-300 text-navy-600 focus:ring-navy-500 dark:border-gray-600 dark:bg-gray-900']) }}>
    <span>{{ $label }}</span>
</label>
