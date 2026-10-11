<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ConnectionTestService;
use App\Services\Google\GoogleApiClient;
use App\Services\GoogleDriveCertificateService;
use App\Services\GoogleDrivePaymentService;
use App\Services\GoogleDrivePhotoService;
use App\Services\SettingsService;
use App\Services\TelegramService;
use App\Support\GoogleDriveFolder;
use App\Support\Money;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings, private AuditLogService $audit) {}

    public function index(TelegramService $telegram, GoogleApiClient $google): View
    {
        return view('settings.index', [
            'settings' => $this->settings,
            'telegramConfigured' => $telegram->configured(),
            'telegramRecipients' => User::query()->whereNotNull('telegram_chat_id')->orderBy('name')->get(['uuid', 'name', 'national_id'])
                ->mapWithKeys(fn (User $u) => [$u->uuid => "{$u->name} ({$u->national_id})"])->all(),
            'googleEmail' => $google->clientEmail(),
            'googleSource' => $google->source(),
            'googleOauthClientId' => $google->oauthClientId(),
            'googleOauthReady' => $google->oauthReady(),
            'googleRedirectUri' => GoogleConnectController::redirectUriFor(),
        ]);
    }

    public function update(Request $request, GoogleDrivePhotoService $photos, GoogleDriveCertificateService $certificates, GoogleDrivePaymentService $paymentDrive, TelegramService $telegram, GoogleApiClient $google): RedirectResponse
    {
        $data = $request->validate([
            'default_class_fee' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
            'shop_enabled' => ['sometimes', 'boolean'],
            'student_email_required' => ['sometimes', 'boolean'],
            'proof_max_kb' => ['required', 'integer', 'min:100', 'max:20480'],
            'footer_text' => ['nullable', 'string', 'max:255'],
            'google_drive_folder' => ['nullable', 'string', 'max:500'],
            'google_drive_certificates_folder' => ['nullable', 'string', 'max:500'],
            'google_drive_payments_folder' => ['nullable', 'string', 'max:500'],
            'telegram_bot_token' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', 'bail', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'google_service_account' => ['nullable', 'bail', 'file', 'max:20'],
            'remove_google_service_account' => ['sometimes', 'boolean'],
            'google_oauth_client_id' => ['nullable', 'string', 'max:255', 'regex:/^[0-9A-Za-z._-]+\.apps\.googleusercontent\.com$/'],
            'google_oauth_client_secret' => ['nullable', 'string', 'max:255'],
        ], [
            'google_oauth_client_id.regex' => 'The Client ID ends with .apps.googleusercontent.com — copy it from Google Cloud → Credentials.',
            'google_service_account.max' => 'The service account key must be the small JSON file from Google Cloud (under 20 KB).',
        ]);

        $actor = $request->user();
        $messages = [];
        $messages[] = $this->saveLogo($request, $actor);
        $messages[] = $this->saveGoogleKey($request, $actor, $google);
        $messages[] = $this->saveOauthClient($data, $actor, $google);

        foreach (['bank_name', 'account_name', 'account_number', 'payment_instructions', 'footer_text'] as $key) {
            $this->settings->set($key, $data[$key] ?? null, $actor);
        }

        $this->settings->set('default_class_fee', Money::normalize($data['default_class_fee']), $actor);
        $this->settings->set('shop_enabled', $request->boolean('shop_enabled') ? '1' : '0', $actor);
        $this->settings->set('student_email_required', $request->boolean('student_email_required') ? '1' : '0', $actor);
        $this->settings->set('proof_max_kb', (string) $data['proof_max_kb'], $actor);

        $messages[] = $this->saveFolder('google_drive_folder', $data['google_drive_folder'] ?? null, $actor, function (string $id) use ($photos): string {
            return $photos->ensureAreaFolders($id)['message'];
        });

        $messages[] = $this->saveFolder('google_drive_certificates_folder', $data['google_drive_certificates_folder'] ?? null, $actor, function (string $id) use ($certificates): string {
            return $certificates->verifyRootFolder($id)['message'];
        });

        $messages[] = $this->saveFolder('google_drive_payments_folder', $data['google_drive_payments_folder'] ?? null, $actor, function (string $id) use ($paymentDrive): string {
            return $paymentDrive->prepareFolders($id)['message'];
        });

        if (blank($data['google_drive_payments_folder'] ?? null) && filled($this->settings->driveFolderId()) && $paymentDrive->enabled() && blank($this->settings->get('google_drive_payments_class_fee'))) {
            $messages[] = $paymentDrive->prepareFolders()['message'];
        }

        if (filled($data['telegram_bot_token'] ?? null)) {
            $result = $telegram->storeToken(trim($data['telegram_bot_token']), $actor);

            if (! $result['ok']) {
                throw ValidationException::withMessages(['telegram_bot_token' => $result['message']]);
            }

            $messages[] = $result['message'];
        } elseif ($request->boolean('remove_telegram_token')) {
            $this->settings->set('telegram_bot_token', null, $actor);
            $this->settings->set('telegram_bot_username', null, $actor);
            $messages[] = 'The Telegram bot was removed.';
        }

        $this->settings->flush();
        $this->audit->record('settings.updated', null, ['keys' => array_keys(array_diff_key($data, ['telegram_bot_token' => true, 'logo' => true, 'google_service_account' => true, 'google_oauth_client_secret' => true]))], $actor);

        return redirect()->route('settings.index')->with('success', trim('Settings saved. '.implode(' ', array_filter($messages))));
    }

    public function testDrive(ConnectionTestService $tests): RedirectResponse
    {
        $result = $tests->googleDrive();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Send a real test message to a connected user or a chat ID.
     */
    public function sendTelegramTest(Request $request, TelegramService $telegram): RedirectResponse
    {
        $data = $request->validate([
            'recipient' => ['nullable', 'uuid', 'required_without:chat_id'],
            'chat_id' => ['nullable', 'string', 'max:64', 'regex:/^(-?\d+|@[A-Za-z0-9_]{5,})$/'],
            'message' => ['required', 'string', 'max:1000'],
        ], [
            'recipient.required_without' => 'Choose a person, or enter a chat ID.',
            'chat_id.regex' => 'A chat ID is a number (e.g. 123456789) or a public @channelname.',
        ]);

        if (! $telegram->configured()) {
            return back()->with('error', 'Save a Telegram bot token first.');
        }

        $recipient = filled($data['recipient'] ?? null) ? User::query()->where('uuid', $data['recipient'])->whereNotNull('telegram_chat_id')->first() : null;
        $chatId = $recipient?->telegram_chat_id ?? ($data['chat_id'] ?? null);

        if (! $chatId) {
            return back()->withInput()->with('error', 'That person has not connected Telegram yet.');
        }

        $result = $telegram->send((string) $chatId, '🔔 <b>Test message</b> from '.e(config('scout.short_name'))."\n".e($data['message']));
        $to = $recipient?->name ?? $chatId;
        $this->audit->record('telegram.test_message_sent', $recipient, ['to' => $recipient ? $recipient->national_id : $chatId, 'ok' => $result['ok']]);

        return back()->withInput()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? "Test message sent to {$to}." : "Could not send to {$to}: {$result['message']}");
    }

    public function testTelegram(ConnectionTestService $tests): RedirectResponse
    {
        $result = $tests->telegram();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Save the OAuth client used by "Connect Google account". A blank secret keeps the saved one.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveOauthClient(array $data, User $actor, GoogleApiClient $google): ?string
    {
        $clientId = trim((string) ($data['google_oauth_client_id'] ?? ''));
        $secret = trim((string) ($data['google_oauth_client_secret'] ?? ''));
        $oldId = (string) $google->oauthClientId();

        if ($clientId === $oldId && $secret === '') {
            return null;
        }

        if ($clientId !== $oldId && $google->oauthConnected()) {
            $google->disconnectOauth();
        }

        $this->settings->set(GoogleApiClient::OAUTH_CLIENT_ID, $clientId !== '' ? $clientId : null, $actor);

        if ($clientId === '') {
            $this->settings->set(GoogleApiClient::OAUTH_CLIENT_SECRET, null, $actor);

            return 'The Google sign-in client was removed.';
        }

        if ($secret !== '') {
            $this->settings->set(GoogleApiClient::OAUTH_CLIENT_SECRET, $secret, $actor);
        }

        $google->forget();
        $this->audit->record('settings.google_oauth_client_saved', null, ['client_id' => $clientId], $actor);

        return $google->oauthReady()
            ? 'Google sign-in details saved. Now press Connect Google account.'
            : 'Client ID saved. Add the Client secret too.';
    }

    /**
     * Save an uploaded Google service-account key (encrypted), or remove the saved one.
     */
    private function saveGoogleKey(Request $request, User $actor, GoogleApiClient $google): ?string
    {
        if ($request->hasFile('google_service_account')) {
            $file = $request->file('google_service_account');
            $key = GoogleApiClient::parseKey((string) @file_get_contents($file->getRealPath() ?: $file->getPathname()));

            if (is_string($key)) {
                throw ValidationException::withMessages(['google_service_account' => $key]);
            }

            $this->settings->set(GoogleApiClient::SETTING_KEY, (string) json_encode($key), $actor);
            $google->forget();
            $this->audit->record('settings.google_key_saved', null, ['client_email' => $key['client_email']], $actor);

            $note = $google->source() === 'server' ? ' The server setting GOOGLE_SERVICE_ACCOUNT_JSON still takes priority.' : '';

            return "Google service account {$key['client_email']} saved. Share your Drive folders and Slides templates with it as an editor.".$note;
        }

        if ($request->boolean('remove_google_service_account') && filled($this->settings->get(GoogleApiClient::SETTING_KEY))) {
            $this->settings->set(GoogleApiClient::SETTING_KEY, null, $actor);
            $google->forget();
            $this->audit->record('settings.google_key_removed', null, [], $actor);

            return 'The Google service account key was removed.';
        }

        return null;
    }

    /**
     * Store an uploaded website logo on the public disk, or remove the current one.
     */
    private function saveLogo(Request $request, User $actor): ?string
    {
        $old = $this->settings->get('site_logo_path');

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $size = Uploads::imageSize($file);

            if ($size === null) {
                throw ValidationException::withMessages(['logo' => 'The logo must be a PNG or JPEG image.']);
            }

            if ($size[0] > 2000 || $size[1] > 2000) {
                throw ValidationException::withMessages(['logo' => 'The logo can be at most 2000 × 2000 pixels.']);
            }

            $path = Uploads::store($file, 'branding', 'logo-'.Str::random(12).'.'.strtolower($file->extension() ?: 'png'), 'public');
            $this->settings->set('site_logo_path', $path, $actor);
            $this->deleteLogo($old);
            $this->audit->record('settings.logo_updated', null, [], $actor);

            return 'The website logo was updated.';
        }

        if ($request->boolean('remove_logo') && $old) {
            $this->settings->set('site_logo_path', null, $actor);
            $this->deleteLogo($old);
            $this->audit->record('settings.logo_removed', null, [], $actor);

            return 'The website logo was removed.';
        }

        return null;
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && str_starts_with($path, 'branding/')) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  callable(string): string  $onSaved
     */
    private function saveFolder(string $key, ?string $input, User $actor, callable $onSaved): ?string
    {
        if (blank($input)) {
            $this->settings->set($key, null, $actor);
            $this->settings->set($key.'_id', null, $actor);

            return null;
        }

        $id = GoogleDriveFolder::idFrom($input);

        if ($id === null) {
            throw ValidationException::withMessages([$key => 'Enter a Google Drive folder link or folder ID.']);
        }

        $changed = $this->settings->get($key.'_id') !== $id;
        $this->settings->set($key, trim($input), $actor);
        $this->settings->set($key.'_id', $id, $actor);

        return $changed ? $onSaved($id) : null;
    }
}
