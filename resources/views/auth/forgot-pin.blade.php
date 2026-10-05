<x-guest-layout title="Forgot PIN">
    <h1 class="mb-1 text-xl font-bold">Forgot your PIN?</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Enter your National ID and the email address saved on your account. We will email you a link to choose a new PIN.</p>

    <form method="POST" action="{{ route('pin.send') }}" class="space-y-4">
        @csrf
        <x-form.input name="national_id" label="National ID" required autofocus class="uppercase"/>
        <x-form.input name="email" label="Email address" type="email" required autocomplete="email"/>
        <button type="submit" class="btn-primary w-full">Email me a reset link</button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">No email on your account? Ask a leader or an administrator to reset your PIN.</p>
    <p class="mt-2 text-center text-sm"><a href="{{ route('login') }}" class="link">Back to sign in</a></p>
</x-guest-layout>
