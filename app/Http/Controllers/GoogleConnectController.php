<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\Google\GoogleApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Sign in with Google": connect a Google account for Drive and Slides
 * instead of uploading a service-account key.
 */
class GoogleConnectController extends Controller
{
    private const STATE_KEY = 'google_oauth_state';

    public function __construct(private GoogleApiClient $google, private AuditLogService $audit) {}

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->google->oauthReady()) {
            return redirect()->route('settings.index')->with('error', 'Save the Google Client ID and Client secret first, then press Connect Google account.');
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away($this->google->authorizationUrl($this->redirectUri(), $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull(self::STATE_KEY);

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('settings.index')->with('error', 'The Google sign-in expired or did not come from this page. Please press Connect Google account again.');
        }

        if ($request->filled('error')) {
            return redirect()->route('settings.index')->with('error', 'Google sign-in was cancelled ('.$request->query('error').').');
        }

        $result = $this->google->exchangeCode((string) $request->query('code'), $this->redirectUri());

        if ($result['ok']) {
            $this->audit->record('settings.google_account_connected', null, ['email' => $this->google->clientEmail()]);
        }

        return redirect()->route('settings.index')->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function disconnect(): RedirectResponse
    {
        $this->google->disconnectOauth();
        $this->audit->record('settings.google_account_disconnected');

        return redirect()->route('settings.index')->with('success', 'The Google account was disconnected.');
    }

    /**
     * The address Google sends people back to. It must be listed in the OAuth client.
     */
    public static function redirectUriFor(): string
    {
        return route('settings.google.callback');
    }

    private function redirectUri(): string
    {
        return self::redirectUriFor();
    }
}
