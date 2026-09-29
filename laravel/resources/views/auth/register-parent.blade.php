<x-guest-layout title="Register as a parent" width="lg">
    <h1 class="mb-1 text-xl font-bold">Register as a parent</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Add your children by National ID. A leader will verify your account before you can sign in.</p>

    <form method="POST" action="{{ route('register.parent') }}" class="space-y-5">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="name" label="Full name" required/>
            <x-form.input name="national_id" label="National ID" required class="uppercase"/>
            <x-form.input name="email" label="Email" type="email" required/>
        </div>
        <livewire:parent-children-lookup :initial="old('children', [])"/>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="pin" label="Choose a PIN" type="password" required help="4 to 32 characters." inputmode="numeric"/>
            <x-form.input name="pin_confirmation" label="Confirm PIN" type="password" required inputmode="numeric"/>
        </div>
        <button type="submit" class="btn-primary w-full">Register</button>
    </form>

    <p class="mt-6 text-center text-sm">Already registered? <a href="{{ route('login') }}" class="link">Sign in</a></p>
</x-guest-layout>
