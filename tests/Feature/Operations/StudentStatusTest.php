<?php

namespace Tests\Feature\Operations;

use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_make_a_scout_inactive_with_a_reason_and_it_is_shown(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['status' => StudentStatus::Active]);

        $this->actingAs($admin)->post(route('students.status', $student), ['status' => 'inactive', 'reason' => 'Moved to another island', 'effective_date' => '2026-11-10'])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(StudentStatus::Inactive, $student->status);
        $this->assertSame('Moved to another island', $student->status_reason);
        $this->assertSame('2026-11-10', $student->status_changed_at->toDateString());
        $this->assertSame($admin->id, $student->status_changed_by);

        $this->actingAs($admin)->get(route('students.show', $student))
            ->assertSee('data-testid="status-reason"', false)
            ->assertSee('Moved to another island');
    }

    public function test_the_date_is_required_and_cannot_be_in_the_future(): void
    {
        $student = Student::factory()->create(['status' => StudentStatus::Active]);

        $this->actingAs($this->admin())->post(route('students.status', $student), ['status' => 'inactive', 'reason' => 'Left'])->assertSessionHasErrors('effective_date');
        $this->actingAs($this->admin())->post(route('students.status', $student), ['status' => 'inactive', 'reason' => 'Left', 'effective_date' => Carbon::parse(scout_today())->addDay()->toDateString()])->assertSessionHasErrors('effective_date');
    }

    public function test_a_reason_is_required_and_pending_registrations_are_not_changed_here(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['status' => StudentStatus::Active]);
        $pending = Student::factory()->create(['status' => StudentStatus::Pending]);

        $this->actingAs($admin)->post(route('students.status', $student), ['status' => 'inactive', 'reason' => '', 'effective_date' => '2026-11-10'])->assertSessionHasErrors('reason');
        $this->assertSame(StudentStatus::Active, $student->fresh()->status);

        $this->actingAs($admin)->post(route('students.status', $pending), ['status' => 'active', 'reason' => 'Because', 'effective_date' => '2026-11-10'])->assertStatus(422);
    }
}
