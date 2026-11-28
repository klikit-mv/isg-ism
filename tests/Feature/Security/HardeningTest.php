<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_carry_security_headers(): void
    {
        $response = $this->get('/login')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_a_user_deactivated_while_signed_in_is_signed_out(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['status' => UserStatus::Inactive])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_pin_cannot_be_guessed_by_spreading_attempts_over_many_addresses(): void
    {
        $user = User::factory()->admin()->create();

        for ($i = 1; $i <= 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->post('/login', ['national_id' => $user->national_id, 'pin' => '000000']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])->post('/login', ['national_id' => $user->national_id, 'pin' => '123456'])
            ->assertSessionHasErrors('national_id');
        $this->assertGuest();
    }

    public function test_push_subscriptions_are_limited_to_browser_push_services(): void
    {
        $user = User::factory()->parentRole()->create();
        $keys = ['p256dh' => 'BKey', 'auth' => 'auth'];

        foreach (['https://169.254.169.254/latest', 'https://internal.example.com/x', 'https://fcm.googleapis.com.evil.test/x', 'https://fcm.googleapis.com:8443/x'] as $endpoint) {
            $this->actingAs($user)->postJson(route('profile.push.subscribe'), ['endpoint' => $endpoint, 'keys' => $keys])->assertUnprocessable();
        }

        $this->actingAs($user)->postJson(route('profile.push.subscribe'), ['endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/abc', 'keys' => $keys])->assertOk();
    }
}
