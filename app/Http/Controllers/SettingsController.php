<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\ConnectionTestService;
use App\Services\GoogleDriveCertificateService;
use App\Services\GoogleDrivePhotoService;
use App\Services\SettingsService;
use App\Services\TelegramService;
use App\Support\GoogleDriveFolder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings, private AuditLogService $audit) {}

    public function index(TelegramService $telegram): View
    {
        return view('settings.index', [
            'settings' => $this->settings,
            'telegramConfigured' => $telegram->configured(),
        ]);
    }

    public function update(Request $request, GoogleDrivePhotoService $photos, GoogleDriveCertificateService $certificates, TelegramService $telegram): RedirectResponse
    {
        $data = $request->validate([
            'default_class_fee' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
            'shop_enabled' => ['sometimes', 'boolean'],
            'proof_max_kb' => ['required', 'integer', 'min:100', 'max:20480'],
            'footer_text' => ['nullable', 'string', 'max:255'],
            'google_drive_folder' => ['nullable', 'string', 'max:500'],
            'google_drive_certificates_folder' => ['nullable', 'string', 'max:500'],
            'telegram_bot_token' => ['nullable', 'string', 'max:200'],
        ]);

        $actor = $request->user();
        $messages = [];

        foreach (['bank_name', 'account_name', 'account_number', 'payment_instructions', 'footer_text'] as $key) {
            $this->settings->set($key, $data[$key] ?? null, $actor);
        }

        $this->settings->set('default_class_fee', Money::normalize($data['default_class_fee']), $actor);
        $this->settings->set('shop_enabled', $request->boolean('shop_enabled') ? '1' : '0', $actor);
        $this->settings->set('proof_max_kb', (string) $data['proof_max_kb'], $actor);

        $messages[] = $this->saveFolder('google_drive_folder', $data['google_drive_folder'] ?? null, $actor, function (string $id) use ($photos): string {
            return $photos->ensureAreaFolders($id)['message'];
        });

        $messages[] = $this->saveFolder('google_drive_certificates_folder', $data['google_drive_certificates_folder'] ?? null, $actor, function (string $id) use ($certificates): string {
            return $certificates->verifyRootFolder($id)['message'];
        });

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
        $this->audit->record('settings.updated', null, ['keys' => array_keys(array_diff_key($data, ['telegram_bot_token' => true]))], $actor);

        return redirect()->route('settings.index')->with('success', trim('Settings saved. '.implode(' ', array_filter($messages))));
    }

    public function testDrive(ConnectionTestService $tests): RedirectResponse
    {
        $result = $tests->googleDrive();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function testTelegram(ConnectionTestService $tests): RedirectResponse
    {
        $result = $tests->telegram();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * @param  callable(string): string  $onSaved
     */
    private function saveFolder(string $key, ?string $input, $actor, callable $onSaved): ?string
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
