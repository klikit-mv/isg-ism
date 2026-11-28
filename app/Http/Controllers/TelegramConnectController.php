<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TelegramConnectController extends Controller
{
    public function __construct(private TelegramService $telegram, private AuditLogService $audit) {}

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->telegram->configured() || ! $this->telegram->botUsername()) {
            return redirect()->route('profile.edit')->with('error', 'Telegram is not set up for this portal yet. Ask an administrator to add the bot in Settings.');
        }

        $token = $this->telegram->issueConnectToken($request->user());
        $request->session()->put('telegram_connect_token', $token);

        return redirect()->route('profile.edit')->with('success', 'Now open the bot in Telegram and press Start. This page connects automatically.');
    }

    /**
     * Check whether the user has pressed Start in the bot. The profile page
     * calls this automatically (JSON) while waiting.
     */
    public function confirm(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->user()->telegram_chat_id === null) {
            $this->telegram->claimPendingStarts();
        }

        $user = $request->user()->fresh();
        $connected = filled($user->telegram_chat_id);

        if ($connected && $request->session()->pull('telegram_connect_token')) {
            $this->audit->record('telegram.connected', $user);
            $this->telegram->send((string) $user->telegram_chat_id, '✅ <b>'.e(config('scout.short_name')).'</b>'."\n".'Hello '.e($user->name).', your account is connected. Notifications will arrive here.');
        }

        if ($request->expectsJson()) {
            return response()->json(['connected' => $connected]);
        }

        return $connected
            ? redirect()->route('profile.edit')->with('success', 'Telegram is connected.')
            : redirect()->route('profile.edit')->with('warning', 'We have not seen your Start message yet. Press Start in the bot, then try again.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->telegram->disconnect($request->user());
        $request->session()->forget('telegram_connect_token');
        $this->audit->record('telegram.disconnected', $request->user());

        return redirect()->route('profile.edit')->with('success', 'Telegram is disconnected.');
    }

    public function test(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->telegram_chat_id) {
            return redirect()->route('profile.edit')->with('error', 'Connect Telegram first.');
        }

        $result = $this->telegram->send($user->telegram_chat_id, '🔔 Test message from <b>'.e(config('scout.short_name')).'</b>. Notifications are working.');

        return redirect()->route('profile.edit')->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Test message sent. Check Telegram.' : $result['message']);
    }
}
