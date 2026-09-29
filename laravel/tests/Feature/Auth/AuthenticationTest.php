<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('National ID');
    }

    public function test_active_user_signs_in_with_national_id_and_pin(): void
    {
        $user = User::factory()->admin()->create(['national_id' => 'A123456']);

        $response = $this->post('/login', ['national_id' => ' a123456 ', 'pin' => '1234']);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_user_id' => $user->id]);
    }

    public function test_wrong_pin_is_refused(): void
    {
        User::factory()->create(['national_id' => 'A123456']);

        $this->post('/login', ['national_id' => 'A123456', 'pin' => '9999'])
            ->assertSessionHasErrors(['national_id' => 'These details do not match an active account.']);

        $this->assertGuest();
    }

    public function test_pending_registration_gets_waiting_message(): void
    {
        User::factory()->unverified()->create(['national_id' => 'A123456']);

        $this->post('/login', ['national_id' => 'A123456', 'pin' => '1234'])
            ->assertSessionHasErrors(['national_id' => 'Your registration is waiting for a leader to verify it.']);

        $this->assertGuest();
    }

    public function test_inactive_verified_account_gets_generic_message(): void
    {
        User::factory()->inactive()->create(['national_id' => 'A123456']);

        $this->post('/login', ['national_id' => 'A123456', 'pin' => '1234'])
            ->assertSessionHasErrors(['national_id' => 'These details do not match an active account.']);

        $this->assertGuest();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function legacyHashes(): array
    {
        return [
            'salt then pin' => ['salt+pin'],
            'pin then salt' => ['pin+salt'],
            'pin only' => ['pin'],
        ];
    }

    #[DataProvider('legacyHashes')]
    public function test_legacy_sha256_pin_is_accepted_once_and_rehashed(string $variant): void
    {
        $hash = match ($variant) {
            'salt+pin' => hash('sha256', 'NaCl4321'),
            'pin+salt' => hash('sha256', '4321NaCl'),
            default => hash('sha256', '4321'),
        };

        $user = User::factory()->create([
            'national_id' => 'A123456',
            'password' => Hash::make(str()->random(40)),
            'legacy_pin_salt' => 'NaCl',
            'legacy_pin_hash' => $hash,
        ]);

        $this->post('/login', ['national_id' => 'A123456', 'pin' => '4321']);

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertNull($user->legacy_pin_hash);
        $this->assertNull($user->legacy_pin_salt);
        $this->assertTrue(Hash::check('4321', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.pin_rehashed']);
    }

    public function test_sign_in_is_throttled_after_five_failures(): void
    {
        User::factory()->create(['national_id' => 'A123456']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['national_id' => 'A123456', 'pin' => '0000']);
        }

        $this->post('/login', ['national_id' => 'A123456', 'pin' => '1234'])
            ->assertSessionHasErrors('national_id');

        $this->assertGuest();
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('national_id'));
    }

    public function test_logout_clears_session_and_is_audited(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue(AuditLog::query()->where('action', 'auth.logout')->where('actor_user_id', $user->id)->exists());
    }

    public function test_pins_never_reach_the_audit_log(): void
    {
        User::factory()->admin()->create(['national_id' => 'A123456']);

        $this->post('/login', ['national_id' => 'A123456', 'pin' => '1234']);

        foreach (AuditLog::all() as $log) {
            $this->assertStringNotContainsString('1234', (string) json_encode($log->details));
        }
    }
}
