<x-app-layout title="Settings">
    <x-page-header title="Settings" description="Fees, payments, shop, storage and notifications.">
        <x-slot:actions>
            <form method="POST" action="{{ route('settings.test.drive') }}">@csrf<button class="btn-secondary">Test Google Drive</button></form>
            <form method="POST" action="{{ route('settings.test.telegram') }}">@csrf<button class="btn-secondary">Check Telegram bot</button></form>
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
                <input type="hidden" name="student_email_required" value="0">
                <x-form.checkbox name="student_email_required" label="Scouts must give an email address" :checked="$settings->studentEmailRequired()"/>
                <p class="-mt-2 text-xs text-gray-500 dark:text-gray-400">Switch off to make the email optional when scouts register or are enrolled. Scouts without an email get no welcome email.</p>
                <x-form.input name="footer_text" label="Footer text" :value="$settings->footerText()"/>
            </div>

            <div class="card space-y-4">
                <h2 class="font-semibold">Google Drive</h2>
                @if ($googleEmail)
                    <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200" data-testid="google-connected">
                        @if ($googleSource === 'oauth')
                            Connected with the Google account <span class="break-all font-mono">{{ $googleEmail }}</span>. Use folders this account can edit.
                        @else
                            Connected with the service account <span class="break-all font-mono">{{ $googleEmail }}</span>{{ $googleSource === 'server' ? ' (set on the server)' : '' }}.
                            Share both folders and every Slides template with this address as an <strong>Editor</strong>.
                        @endif
                    </div>
                @else
                    <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                        Not connected. Photos and certificates are stored on the server until you connect Google.
                    </div>
                @endif

                <div class="space-y-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <h3 class="text-sm font-semibold">Connect a Google account <span class="font-normal text-gray-500">(recommended, no key file)</span></h3>
                    <details class="text-sm text-gray-600 dark:text-gray-300" @unless ($googleOauthReady) open @endunless>
                        <summary class="cursor-pointer font-medium text-navy-700 dark:text-navy-300">How to get the Client ID and secret</summary>
                        <ol class="mt-2 list-decimal space-y-1 pl-5">
                            <li>In <a class="link" href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a>, pick or create a project.</li>
                            <li>Enable the <a class="link" href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank" rel="noopener">Google Drive API</a> and the <a class="link" href="https://console.cloud.google.com/apis/library/slides.googleapis.com" target="_blank" rel="noopener">Google Slides API</a>.</li>
                            <li>Open <a class="link" href="https://console.cloud.google.com/auth/branding" target="_blank" rel="noopener">Google Auth Platform</a> (OAuth consent screen): choose <em>External</em>, fill in the app name and emails, then under <em>Audience</em> press <strong>Publish app</strong> so the connection does not expire after 7 days.</li>
                            <li>Open <a class="link" href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Credentials</a> → <strong>Create credentials → OAuth client ID</strong> → type <em>Web application</em>.</li>
                            <li>Under <em>Authorised redirect URIs</em> add exactly the address below, then press <strong>Create</strong>.</li>
                            <li>Copy the <strong>Client ID</strong> and <strong>Client secret</strong> here, press <strong>Save settings</strong>, then <strong>Connect Google account</strong> and allow access. Google may warn that the app is unverified: choose <em>Advanced → Go to …</em> to continue.</li>
                        </ol>
                    </details>
                    <div>
                        <span class="label">Authorised redirect URI</span>
                        <div class="flex items-center gap-2">
                            <code class="block flex-1 break-all rounded bg-gray-100 px-2 py-1.5 text-xs dark:bg-gray-900" data-testid="google-redirect-uri">{{ $googleRedirectUri }}</code>
                            <button type="button" class="btn-secondary btn-sm" onclick="window.copyText(@js($googleRedirectUri), this)">Copy</button>
                        </div>
                        @unless (str_starts_with($googleRedirectUri, 'https://') || preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?/#', $googleRedirectUri))
                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Google only accepts https addresses on a public domain, or http://localhost. Open the portal on such an address before connecting.</p>
                        @endunless
                    </div>
                    <x-form.input name="google_oauth_client_id" label="Client ID" :value="$googleOauthClientId" placeholder="1234-abc.apps.googleusercontent.com"/>
                    <x-form.input name="google_oauth_client_secret" label="{{ $googleOauthReady ? 'Client secret (leave blank to keep the saved one)' : 'Client secret' }}" type="password" autocomplete="off"/>
                    <div class="flex flex-wrap gap-2">
                        @if ($googleOauthReady)
                            <a href="{{ route('settings.google.connect') }}" class="btn-accent btn-sm">{{ $googleSource === 'oauth' ? 'Reconnect Google account' : 'Connect Google account' }}</a>
                        @endif
                        @if ($googleSource === 'oauth')
                            <button type="submit" form="google-disconnect" class="btn-secondary btn-sm">Disconnect</button>
                        @endif
                    </div>
                </div>

                <details class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700" @if ($googleSource === 'settings') open @endif>
                    <summary class="cursor-pointer font-semibold">Or use a service account key (JSON file)</summary>
                    <div class="mt-3 space-y-3">
                        <p class="text-gray-600 dark:text-gray-300">In Google Cloud: <em>IAM &amp; Admin → Service Accounts</em> → create one → <em>Keys → Add key → JSON</em>. Service accounts cannot store files in a personal My Drive, so use a <strong>shared drive</strong> with this option.</p>
                        <x-form.file-drop name="google_service_account" label="Service account key (JSON)" accept=".json,application/json" help="Stored encrypted. It is never shown again."/>
                        @if ($googleSource === 'settings')
                            <x-form.checkbox name="remove_google_service_account" label="Remove the saved key and disconnect Google"/>
                        @endif
                    </div>
                </details>

                <x-form.input name="google_drive_folder" label="Photos folder (link or ID)" :value="$settings->get('google_drive_folder')" help="Students, Shop, Badges and Signatures sub-folders are created inside it."/>
                <x-form.input name="google_drive_certificates_folder" label="Certificates folder (link or ID)" :value="$settings->get('google_drive_certificates_folder')" help="Each scout gets a sub-folder for their PDFs."/>
                <x-form.input name="google_drive_payments_folder" label="Finance folder (link or ID, optional)" :value="$settings->get('google_drive_payments_folder')" help="Payment proofs, bank deposit slips and receipts. Leave empty to use a \"Finance\" folder created inside the main Drive folder. A sub-folder per module is created, and inside each one a folder per person (name and National ID), so one person's documents sit together: Class fees, Annual fees, Shop purchases, Events, Bank deposits, Bank expenses."/>
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

    <form id="google-disconnect" method="POST" action="{{ route('settings.google.disconnect') }}" class="hidden">@csrf</form>

    @if ($telegramConfigured)
        <section class="card mt-6 max-w-2xl" data-testid="telegram-test-message">
            <h2 class="mb-1 font-semibold">Send a Telegram test message</h2>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Sends a real message through the bot. People appear in the list once they connect Telegram in their profile.</p>
            <form method="POST" action="{{ route('settings.telegram.test-message') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.select name="recipient" label="Send to" :options="$telegramRecipients" :placeholder="$telegramRecipients ? 'Choose a person' : 'Nobody has connected yet'"/>
                    <x-form.input name="chat_id" label="…or a chat ID" placeholder="123456789" help="Only needed for someone not in the list."/>
                </div>
                <x-form.textarea name="message" label="Message" :value="'Hello! This is a test message from '.config('scout.short_name').'.'" rows="2"/>
                <button type="submit" class="btn-primary">Send test message</button>
            </form>
        </section>
    @endif
</x-app-layout>
