{{-- Drag-and-drop file area backed by a normal file input. --}}
@props(['name', 'label' => null, 'accept' => null, 'help' => null])

<div x-data="{ fileName: '', over: false }">
    @if ($label)
        <span class="label">{{ $label }}</span>
    @endif
    <label
        class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-6 text-center text-sm transition"
        :class="over ? 'border-navy-500 bg-navy-50 dark:bg-navy-900/30' : 'border-gray-300 dark:border-gray-600'"
        x-on:dragover.prevent="over = true"
        x-on:dragleave.prevent="over = false"
        x-on:drop.prevent="over = false; $refs.input.files = $event.dataTransfer.files; fileName = $event.dataTransfer.files[0]?.name || ''"
    >
        <svg class="mb-2 h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
        <span class="text-gray-600 dark:text-gray-300" x-text="fileName || 'Drop a file here or click to choose'"></span>
        <input x-ref="input" type="file" name="{{ $name }}" @if ($accept) accept="{{ $accept }}" @endif class="sr-only"
            x-on:change="fileName = $event.target.files[0]?.name || ''" {{ $attributes }}>
    </label>
    @if ($help)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror
</div>
