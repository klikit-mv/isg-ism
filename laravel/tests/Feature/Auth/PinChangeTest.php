<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PinChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_changes_own_pin_with_current_pin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', [
            'current_pin' => '1234',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('5678', $user->fresh()->password));
    }

    public function test_wrong_current_pin_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', [
            'current_pin' => '0000',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ])->assertSessionHasErrorsIn('updatePin', 'current_pin');

        $this->assertTrue(Hash::check('1234', $user->fresh()->password));
    }

    public function test_new_pin_must_be_four_to_thirty_two_characters_and_confirmed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', ['current_pin' => '1234', 'pin' => '12', 'pin_confirmation' => '12'])
            ->assertSessionHasErrorsIn('updatePin', 'pin');

        $this->actingAs($user)->put('/password', ['current_pin' => '1234', 'pin' => '5678', 'pin_confirmation' => '8765'])
            ->assertSessionHasErrorsIn('updatePin', 'pin');
    }
}
