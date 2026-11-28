<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Forgotten PIN: an emailed, single-use link (valid for 60 minutes) lets people choose a new one.
 */
class PinResetController extends Controller
{
    private const MINUTES = 60;

    public function request(): View
    {
        return view('auth.forgot-pin');
    }

    public function send(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'national_id' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()
            ->where('national_id', strtoupper(trim($data['national_id'])))
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])
            ->first();

        // The same answer whether or not the details match, so nobody can probe for accounts.
        if ($user && $user->isActive()) {
            $token = Str::random(48);
            DB::table('pin_reset_tokens')->where('user_id', $user->id)->delete();
            DB::table('pin_reset_tokens')->insert([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(self::MINUTES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $url = route('pin.reset', ['token' => $token]);

            try {
                Mail::raw(
                    "Hello {$user->name},\n\nUse this link to choose a new PIN (valid for ".self::MINUTES." minutes):\n{$url}\n\nIf you did not ask for this, ignore this email: your PIN has not changed.\n\n".config('scout.name'),
                    fn ($message) => $message->to($user->email)->subject('Reset your PIN'),
                );
                $audit->record('auth.pin_reset_requested', $user);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'If those details match an active account, an email with a reset link is on its way. It is valid for '.self::MINUTES.' minutes.');
    }

    public function edit(string $token): View
    {
        abort_if($this->find($token) === null, 404);

        return view('auth.reset-pin', ['token' => $token]);
    }

    public function update(Request $request, string $token, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'string', 'min:4', 'max:32', 'confirmed']]);
        $row = $this->find($token);

        if ($row === null) {
            return redirect()->route('pin.forgot')->with('error', 'That link has expired or was already used. Ask for a new one.');
        }

        $user = User::query()->findOrFail($row->user_id);
        $user->forceFill(['password' => $data['pin'], 'legacy_pin_hash' => null, 'legacy_pin_salt' => null])->save();

        DB::table('pin_reset_tokens')->where('user_id', $user->id)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $audit->record('auth.pin_reset', $user);

        return redirect()->route('login')->with('success', 'Your PIN was changed. Sign in with the new PIN.');
    }

    private function find(string $token): ?object
    {
        return DB::table('pin_reset_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
    }
}
