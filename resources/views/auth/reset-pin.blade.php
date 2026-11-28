<x-guest-layout title="Choose a new PIN">
    <h1 class="mb-1 text-xl font-bold">Choose a new PIN</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">At least 4 characters.</p>

    <form method="POST" action="{{ route('pin.update', $token) }}" class="space-y-4">
        @csrf
        <x-form.input name="pin" label="New PIN" type="password" required autofocus autocomplete="new-password" inputmode="numeric"/>
        <x-form.input name="pin_confirmation" label="Repeat the new PIN" type="password" required autocomplete="new-password" inputmode="numeric"/>
        <button type="submit" class="btn-primary w-full">Save new PIN</button>
    </form>
</x-guest-layout>
