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

    @if (app()->environment('local') && \App\Models\User::query()->where('national_id', \Database\Seeders\DemoSeeder::sampleAccounts()[0]['national_id'])->exists())
        <div class="mt-6 rounded-lg border border-dashed border-gold-400 bg-gold-50 p-4 text-sm dark:border-gold-700 dark:bg-gold-900/20" data-testid="sample-logins">
            <p class="mb-2 font-semibold text-gold-800 dark:text-gold-200">Sample logins (local only)</p>
            <table class="w-full text-left">
                @foreach (\Database\Seeders\DemoSeeder::sampleAccounts() as $account)
                    <tr>
                        <td class="py-0.5 text-gray-600 dark:text-gray-300">{{ $account['role'] }}</td>
                        <td class="py-0.5 font-mono">{{ $account['national_id'] }}</td>
                        <td class="py-0.5 text-right">
                            <button type="button" class="link text-xs" data-national-id="{{ $account['national_id'] }}" data-pin="{{ \Database\Seeders\DemoSeeder::samplePin() }}"
                                onclick="document.getElementById('national_id').value = this.dataset.nationalId; document.getElementById('pin').value = this.dataset.pin; document.getElementById('pin').focus();">Use</button>
                        </td>
                    </tr>
                @endforeach
            </table>
            <p class="mt-2 text-gray-600 dark:text-gray-300">PIN for all: <span class="font-mono font-semibold">{{ \Database\Seeders\DemoSeeder::samplePin() }}</span></p>
        </div>
    @endif

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
