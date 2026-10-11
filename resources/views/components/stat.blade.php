@props(['label', 'value', 'tone' => 'navy'])

<div class="card min-w-0 !p-3 sm:!p-4">
    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
    <div class="mt-1 break-words text-base font-bold sm:text-xl {{ $tone === 'gold' ? 'text-gold-600 dark:text-gold-400' : 'text-navy-800 dark:text-navy-200' }}">{{ $value }}</div>
</div>
