<div x-data="{ mode: window.scoutTheme ? window.scoutTheme.current() : 'system' }" class="inline-flex rounded-lg border border-gray-200 p-0.5 text-xs dark:border-gray-700" role="group" aria-label="Theme">
    @foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'Auto'] as $mode => $label)
        <button type="button" x-on:click="mode = '{{ $mode }}'; window.scoutTheme.set('{{ $mode }}')"
            :class="mode === '{{ $mode }}' ? 'bg-navy-700 text-white' : 'text-gray-600 dark:text-gray-300'"
            class="rounded-md px-2 py-1">{{ $label }}</button>
    @endforeach
</div>
