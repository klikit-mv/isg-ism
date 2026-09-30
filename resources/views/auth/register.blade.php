<x-guest-layout title="Register as a scout" width="lg">
    <h1 class="mb-1 text-xl font-bold">Register as a scout</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">A leader will verify your registration before you can sign in.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-6">
        @csrf
        @include('students.partials.fields')
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="pin" label="Choose a PIN" type="password" required help="4 to 32 characters." inputmode="numeric"/>
            <x-form.input name="pin_confirmation" label="Confirm PIN" type="password" required inputmode="numeric"/>
        </div>
        <button type="submit" class="btn-primary w-full">Register</button>
    </form>

    <p class="mt-6 text-center text-sm">Already registered? <a href="{{ route('login') }}" class="link">Sign in</a></p>
</x-guest-layout>
