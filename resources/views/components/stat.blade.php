@props(['label', 'value', 'tone' => 'navy'])

<div class="card !p-4">
    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
    <div class="mt-1 text-xl font-bold {{ $tone === 'gold' ? 'text-gold-600 dark:text-gold-400' : 'text-navy-800 dark:text-navy-200' }}">{{ $value }}</div>
</div>
