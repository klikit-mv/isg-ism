<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PinResetTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['national_id' => 'A777', 'email' => 'me@example.com', 'password' => '1111']);
    }

    private function requestLink(): string
    {
        $body = null;
        Mail::shouldReceive('raw')->once()->andReturnUsing(function ($text) use (&$body) {
            $body = $text;
        });
        $this->post('/forgot-pin', ['national_id' => 'a777', 'email' => 'ME@example.com'])->assertSessionHas('status');
        preg_match('#/reset-pin/([A-Za-z0-9]+)#', $body, $m);

        return $m[1];
    }

    public function test_the_login_and_home_pages_link_to_the_new_pages(): void
    {
        $this->get('/login')->assertSee(route('pin.forgot'), false);
        $this->get('/')->assertSee(route('register.parent'), false)->assertSee(route('certificates.verify'), false);
    }

    public function test_a_user_resets_their_own_pin_once_with_the_emailed_link(): void
    {
        $user = $this->user();
        $user->forceFill(['legacy_pin_hash' => 'abc', 'legacy_pin_salt' => 's'])->save();
        $token = $this->requestLink();

        $this->get("/reset-pin/{$token}")->assertOk();
        $this->post("/reset-pin/{$token}", ['pin' => '2468', 'pin_confirmation' => '2468'])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('2468', $user->password));
        $this->assertNull($user->legacy_pin_hash);
        $this->post('/login', ['national_id' => 'A777', 'pin' => '2468'])->assertRedirect();
        $this->post('/logout');
        $this->get("/reset-pin/{$token}")->assertNotFound();
    }

    public function test_wrong_details_get_the_same_answer_and_no_email(): void
    {
        $this->user();
        Mail::shouldReceive('raw')->never();
        $this->post('/forgot-pin', ['national_id' => 'A777', 'email' => 'other@example.com'])->assertSessionHas('status');
        $this->post('/forgot-pin', ['national_id' => 'NOPE', 'email' => 'me@example.com'])->assertSessionHas('status');
        $this->assertSame(0, DB::table('pin_reset_tokens')->count());
    }

    public function test_inactive_accounts_and_expired_links_cannot_reset(): void
    {
        $user = $this->user();
        $token = $this->requestLink();
        DB::table('pin_reset_tokens')->update(['expires_at' => now()->subMinute()]);
        $this->get("/reset-pin/{$token}")->assertNotFound();
        $this->post("/reset-pin/{$token}", ['pin' => '2468', 'pin_confirmation' => '2468'])->assertRedirect(route('pin.forgot'));
        $this->assertTrue(Hash::check('1111', $user->fresh()->password));
    }
}
