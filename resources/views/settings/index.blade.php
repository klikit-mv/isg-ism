<x-app-layout title="Settings">
    <x-page-header title="Settings" description="Fees, payments, shop, storage and notifications.">
        <x-slot:actions>
            <form method="POST" action="{{ route('settings.test.drive') }}">@csrf<button class="btn-secondary">Test Google Drive</button></form>
            <form method="POST" action="{{ route('settings.test.telegram') }}">@csrf<button class="btn-secondary">Test Telegram</button></form>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('settings.update') }}" class="grid gap-6 lg:grid-cols-2">
        @csrf
        <section class="card space-y-4">
            <h2 class="font-semibold">Fees and payments</h2>
            <x-form.input name="default_class_fee" label="Default class fee" type="number" step="0.01" min="0" :value="$settings->defaultClassFee()" required/>
            <x-form.input name="bank_name" label="Bank name" :value="$settings->bankName()"/>
            <x-form.input name="account_name" label="Account name" :value="$settings->accountName()"/>
            <x-form.input name="account_number" label="Account number" :value="$settings->accountNumber()"/>
            <x-form.textarea name="payment_instructions" label="Payment instructions" :value="$settings->paymentInstructions()"/>
            <x-form.input name="proof_max_kb" label="Largest proof file (KB)" type="number" min="100" max="20480" :value="$settings->proofMaxKb()" required help="Accepted types: {{ strtoupper(implode(', ', $settings->proofMimes())) }}."/>
        </section>

        <section class="space-y-6">
            <div class="card space-y-4">
                <h2 class="font-semibold">Shop and portal</h2>
                <input type="hidden" name="shop_enabled" value="0">
                <x-form.checkbox name="shop_enabled" label="The shop is open" :checked="$settings->shopEnabled()"/>
                <x-form.input name="footer_text" label="Footer text" :value="$settings->footerText()"/>
            </div>

            <div class="card space-y-4">
                <h2 class="font-semibold">Google Drive</h2>
                <x-form.input name="google_drive_folder" label="Photos folder (link or ID)" :value="$settings->get('google_drive_folder')" help="Students, Shop, Badges and Signatures sub-folders are created inside it."/>
                <x-form.input name="google_drive_certificates_folder" label="Certificates folder (link or ID)" :value="$settings->get('google_drive_certificates_folder')" help="Each scout gets a sub-folder for their PDFs."/>
            </div>

            <div class="card space-y-4">
                <h2 class="font-semibold">Telegram</h2>
                @if ($telegramConfigured)
                    <p class="text-sm text-emerald-700 dark:text-emerald-300">A bot is connected{{ $settings->telegramBotUsername() ? ' (@'.$settings->telegramBotUsername().')' : '' }}. The token is stored encrypted and never shown.</p>
                    <x-form.checkbox name="remove_telegram_token" label="Remove the bot"/>
                @endif
                <x-form.input name="telegram_bot_token" label="{{ $telegramConfigured ? 'Replace bot token' : 'Bot token' }}" type="password" autocomplete="off" help="From @BotFather. The bot must not have a webhook set."/>
            </div>
        </section>

        <div class="lg:col-span-2"><button class="btn-primary">Save settings</button></div>
    </form>
</x-app-layout>
