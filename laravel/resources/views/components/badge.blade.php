@props(['value' => null, 'tone' => null])

@php
    if ($value instanceof \BackedEnum && method_exists($value, 'badgeClasses')) {
        $classes = $value->badgeClasses();
        $text = $value->label();
    } else {
        $classes = match ($tone) {
            'green' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'red' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
            'blue' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
            'purple' => 'bg-navy-100 text-navy-800 dark:bg-navy-900/60 dark:text-navy-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        };
        $text = $value;
    }
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium '.$classes]) }}>{{ $text ?? $slot }}</span>
