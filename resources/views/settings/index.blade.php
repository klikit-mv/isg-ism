<x-app-layout title="Settings">
    <x-page-header title="Settings" description="Fees, payments, shop, storage and notifications.">
        <x-slot:actions>
            <form method="POST" action="{{ route('settings.test.drive') }}">@csrf<button class="btn-secondary">Test Google Drive</button></form>
            <form method="POST" action="{{ route('settings.test.telegram') }}">@csrf<button class="btn-secondary">Test Telegram</button></form>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-2">
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
                <h2 class="font-semibold">Website logo</h2>
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-navy-800 p-1">
                        <x-logo class="h-14 w-14"/>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $settings->logoPath() ? 'Your uploaded logo is in use.' : 'The built-in logo is in use.' }}
                        Shown in the header, on the sign-in page, as the browser icon and on certificates.
                    </p>
                </div>
                <x-form.file-drop name="logo" label="Upload a new logo" accept="image/png,image/jpeg" help="PNG or JPEG, up to 2 MB. A square image with a transparent background works best."/>
                @if ($settings->logoPath())
                    <x-form.checkbox name="remove_logo" label="Remove the uploaded logo and use the built-in one"/>
                @endif
            </div>

            <div class="card space-y-4">
                <h2 class="font-semibold">Shop and portal</h2>
                <input type="hidden" name="shop_enabled" value="0">
                <x-form.checkbox name="shop_enabled" label="The shop is open" :checked="$settings->shopEnabled()"/>
                <x-form.input name="footer_text" label="Footer text" :value="$settings->footerText()"/>
            </div>

            <div class="card space-y-4">
                <h2 class="font-semibold">Google Drive</h2>
                @if ($googleEmail)
                    <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200" data-testid="google-connected">
                        Connected as <span class="break-all font-mono">{{ $googleEmail }}</span>{{ $googleSource === 'server' ? ' (set on the server)' : '' }}.
                        Share both folders and every Slides template with this address as an <strong>Editor</strong>.
                    </div>
                @else
                    <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                        Not connected. Photos and certificates are stored on the server until you upload a service account key.
                    </div>
                @endif

                <details class="text-sm text-gray-600 dark:text-gray-300" @unless ($googleEmail) open @endunless>
                    <summary class="cursor-pointer font-medium text-navy-700 dark:text-navy-300">How to connect Google Drive</summary>
                    <ol class="mt-2 list-decimal space-y-1 pl-5">
                        <li>Open <a class="link" href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a> and create (or pick) a project.</li>
                        <li>Under <em>APIs &amp; Services → Library</em>, enable the <strong>Google Drive API</strong> and the <strong>Google Slides API</strong>.</li>
                        <li>Under <em>IAM &amp; Admin → Service Accounts</em>, create a service account (no roles needed).</li>
                        <li>Open it, go to <em>Keys → Add key → Create new key → JSON</em> and download the file.</li>
                        <li>Upload that file below and press <strong>Save settings</strong>.</li>
                        <li>In Google Drive, share your photos folder and certificates folder (and each Slides template) with the service account's email as <strong>Editor</strong>, then paste the folder links below and save.</li>
                        <li>Press <strong>Test Google Drive</strong> at the top of this page.</li>
                    </ol>
                </details>

                <x-form.file-drop name="google_service_account" label="Service account key (JSON)" accept=".json,application/json" help="Stored encrypted. It is never shown again."/>
                @if ($googleSource === 'settings')
                    <x-form.checkbox name="remove_google_service_account" label="Remove the saved key and disconnect Google"/>
                @endif
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
