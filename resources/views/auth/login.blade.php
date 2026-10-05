<x-guest-layout title="Sign in">
    <h1 class="mb-1 text-xl font-bold">Sign in</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Use your National ID and PIN.</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-form.input name="national_id" label="National ID" required autofocus autocomplete="username" class="uppercase"/>
        <x-form.input name="pin" label="PIN" type="password" required autocomplete="current-password" inputmode="numeric"/>
        <x-form.checkbox name="remember" label="Keep me signed in"/>
        <button type="submit" class="btn-primary w-full">Sign in</button>
    </form>

    <div class="mt-6 space-y-2 border-t border-gray-100 pt-4 text-center text-sm dark:border-gray-700">
        @if (Route::has('register'))
            <p>New scout? <a href="{{ route('register') }}" class="link">Register as a scout</a></p>
            <p>Parent? <a href="{{ route('register.parent') }}" class="link">Register as a parent</a></p>
        @endif
        <p><a href="{{ route('home') }}" class="link">See upcoming events</a></p>
        @if (Route::has('certificates.verify'))
            <p><a href="{{ route('certificates.verify') }}" class="link">Verify a certificate</a></p>
        @endif
    </div>
</x-guest-layout>
