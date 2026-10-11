@props(['name', 'title' => null, 'show' => false, 'maxWidth' => '2xl'])

@php
    $width = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl', '4xl' => 'sm:max-w-4xl'][$maxWidth];
@endphp

<div
    x-data="{ show: @js($show) }"
    x-init="$watch('show', v => document.body.classList.toggle('overflow-y-hidden', v))"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
    role="dialog"
    aria-modal="true"
>
    <div x-show="show" class="fixed inset-0 bg-gray-900/60" x-on:click="show = false" x-transition.opacity></div>

    <div x-show="show" x-transition class="relative mx-auto mt-10 w-full rounded-xl bg-white shadow-xl dark:bg-gray-800 {{ $width }}">
        @if ($title)
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h2>
                <button type="button" class="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" x-on:click="show = false" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif
        <div class="px-5 py-4">
            {{ $slot }}
        </div>
    </div>
</div>
