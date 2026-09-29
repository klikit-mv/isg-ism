{{-- A button that opens a confirmation modal and then submits a form. --}}
@props(['action', 'method' => 'POST', 'label', 'title' => 'Please confirm', 'message' => 'Are you sure?', 'confirm' => 'Confirm', 'variant' => 'danger', 'size' => 'sm'])

@php $id = 'confirm-'.\Illuminate\Support\Str::random(8); @endphp

<button type="button" class="btn-{{ $variant }} {{ $size === 'sm' ? 'btn-sm' : '' }}" x-data x-on:click="$dispatch('open-modal', '{{ $id }}')">{{ $label }}</button>

<x-modal :name="$id" :title="$title" maxWidth="md">
    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $message }}</p>
        {{ $slot }}
        <div class="flex justify-end gap-2">
            <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', '{{ $id }}')">Cancel</button>
            <button type="submit" class="btn-{{ $variant }}">{{ $confirm }}</button>
        </div>
    </form>
</x-modal>
