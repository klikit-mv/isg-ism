<?php

namespace Tests\Feature\Administration;

use App\Enums\Role;
use App\Models\ParentStudentLink;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_links_parent_and_adds_parent_role(): void
    {
        $user = User::factory()->create();
        $student = Student::factory()->create();

        $this->actingAs($this->admin())->post('/parent-links', ['parent_user_id' => $user->id, 'student_id' => $student->id])
            ->assertSessionHas('success');

        $this->assertTrue($user->fresh()->hasRole(Role::Parent));
        $this->assertSame([$student->id], $user->fresh()->approvedChildIds());
    }

    public function test_a_scout_can_have_only_one_open_parent(): void
    {
        $student = Student::factory()->create();
        $this->parentOf($student);
        $second = User::factory()->create();

        $this->actingAs($this->admin())->post('/parent-links', ['parent_user_id' => $second->id, 'student_id' => $student->id])
            ->assertSessionHas('error', 'This scout is already added under another parent.');
    }

    public function test_reopening_a_link_fails_when_another_parent_holds_the_scout(): void
    {
        $student = Student::factory()->create();
        $old = User::factory()->create();
        $link = ParentStudentLink::query()->create(['parent_user_id' => $old->id, 'student_id' => $student->id, 'status' => 'rejected']);
        $this->parentOf($student);

        $this->actingAs($this->admin())->post("/parent-links/{$link->uuid}", ['status' => 'approved'])
            ->assertSessionHas('error');
        $this->assertSame('rejected', $link->fresh()->status->value);
    }

    public function test_leaders_cannot_manage_parent_links(): void
    {
        $this->actingAs($this->leader())->get('/parent-links')->assertForbidden();
    }
}
