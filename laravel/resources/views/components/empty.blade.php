@props(['message' => 'Nothing to show yet.'])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400']) }}>
    {{ $message }}
    {{ $slot }}
</div>
