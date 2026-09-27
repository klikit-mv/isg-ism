<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TelegramConnectController extends Controller
{
    public function __construct(private TelegramService $telegram, private AuditLogService $audit) {}

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($this->telegram->configured(), 404);

        $token = $this->telegram->issueConnectToken($request->user());
        $request->session()->put('telegram_connect_token', $token);

        return redirect()->route('profile.edit')->with('success', 'Open the bot in Telegram and press Start, then confirm here.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $this->telegram->claimPendingStarts();
        $user = $request->user()->fresh();

        if ($user->telegram_chat_id) {
            $request->session()->forget('telegram_connect_token');
            $this->audit->record('telegram.connected', $user);

            return redirect()->route('profile.edit')->with('success', 'Telegram is connected.');
        }

        return redirect()->route('profile.edit')->with('warning', 'We have not seen your Start message yet. Press Start in the bot, then try again.');
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
        $sent = $user->telegram_chat_id && $this->telegram->sendMessage($user->telegram_chat_id, 'Test message from '.config('scout.short_name').'.');

        return redirect()->route('profile.edit')->with($sent ? 'success' : 'error', $sent ? 'Test message sent.' : 'The test message could not be sent.');
    }
}
