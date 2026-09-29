<?php

namespace Tests\Feature\Registration;

use App\Enums\StudentStatus;
use App\Enums\UserStatus;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ScoutAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ScoutRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'index_number' => 'IX1001',
            'national_id' => 'a445566',
            'name' => 'Ahmed Scout',
            'email' => 'ahmed@example.com',
            'gender' => 'Male',
            'permanent_address' => 'Male, Maldives',
            'present_address' => 'Male, Maldives',
            'date_of_birth' => '2012-04-01',
            'parent_name' => 'Ibrahim',
            'primary_mobile' => '7771234',
            'section' => 'Scout',
            'pin' => '1357',
            'pin_confirmation' => '1357',
        ], $overrides);
    }

    public function test_guest_registers_as_pending_scout_and_staff_are_notified(): void
    {
        Notification::fake();
        $leader = $this->leader();

        $this->post('/register', $this->payload())->assertRedirect(route('login'));

        $student = Student::query()->where('national_id', 'A445566')->firstOrFail();
        $this->assertSame(StudentStatus::Pending, $student->status);
        $this->assertSame(UserStatus::Inactive, $student->user->status);
        $this->assertTrue($student->user->hasRole('student'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.registered']);
        Notification::assertSentTo($leader, ScoutAlert::class);
    }

    public function test_registration_rejects_duplicate_national_id_across_users(): void
    {
        User::factory()->create(['national_id' => 'A445566']);

        $this->post('/register', $this->payload())->assertSessionHasErrors('national_id');
    }

    public function test_date_of_birth_must_be_in_the_past(): void
    {
        $this->post('/register', $this->payload(['date_of_birth' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('date_of_birth');
    }

    public function test_pending_scout_cannot_sign_in_until_verified(): void
    {
        $this->post('/register', $this->payload());

        $this->post('/login', ['national_id' => 'A445566', 'pin' => '1357'])
            ->assertSessionHasErrors(['national_id' => 'Your registration is waiting for a leader to verify it.']);
        $this->assertGuest();

        $leader = $this->leader();
        $student = Student::query()->where('national_id', 'A445566')->firstOrFail();
        $this->actingAs($leader)->post("/students/{$student->uuid}/verify")->assertSessionHas('success');
        auth()->logout();

        $this->assertSame(StudentStatus::Active, $student->fresh()->status);
        $this->post('/login', ['national_id' => 'A445566', 'pin' => '1357']);
        $this->assertAuthenticatedAs($student->user->fresh());
    }

    public function test_declined_registration_stays_inactive(): void
    {
        $this->post('/register', $this->payload());
        $student = Student::query()->where('national_id', 'A445566')->firstOrFail();

        $this->actingAs($this->leader())->post("/students/{$student->uuid}/reject");
        auth()->logout();

        $this->assertSame(StudentStatus::Inactive, $student->fresh()->status);
        $this->post('/login', ['national_id' => 'A445566', 'pin' => '1357'])
            ->assertSessionHasErrors(['national_id' => 'These details do not match an active account.']);
    }

    public function test_students_and_parents_cannot_verify_registrations(): void
    {
        $pending = Student::factory()->pending()->create();

        $this->actingAs($this->studentUser())->post("/students/{$pending->uuid}/verify")->assertForbidden();
        $this->actingAs($this->parentOf())->post("/students/{$pending->uuid}/verify")->assertForbidden();

        $this->assertSame(StudentStatus::Pending, $pending->fresh()->status);
    }
}
