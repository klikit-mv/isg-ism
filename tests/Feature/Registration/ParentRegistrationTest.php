<?php

namespace Tests\Feature\Registration;

use App\Enums\ParentLinkStatus;
use App\Enums\UserStatus;
use App\Livewire\ParentChildrenLookup;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ParentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $children
     * @return array<string, mixed>
     */
    private function payload(array $children): array
    {
        return [
            'name' => 'Fathimath Parent',
            'national_id' => 'a778899',
            'email' => 'parent@example.com',
            'pin' => '2468',
            'pin_confirmation' => '2468',
            'children' => $children,
        ];
    }

    public function test_guest_registers_as_parent_with_pending_links(): void
    {
        $child = Student::factory()->create(['national_id' => 'A1000001']);

        $this->post('/register/parent', $this->payload(['a1000001']))->assertRedirect(route('login'));

        $parent = User::query()->where('national_id', 'A778899')->firstOrFail();
        $this->assertSame(UserStatus::Inactive, $parent->status);
        $this->assertTrue($parent->hasRole('parent'));
        $this->assertDatabaseHas('parent_student_links', ['parent_user_id' => $parent->id, 'student_id' => $child->id, 'status' => 'pending']);
    }

    public function test_scout_with_existing_parent_is_refused(): void
    {
        $child = Student::factory()->create(['national_id' => 'A1000001']);
        $this->parentOf($child);

        $this->post('/register/parent', $this->payload(['A1000001']))
            ->assertSessionHas('error', 'This scout is already added under another parent.');

        $this->assertDatabaseMissing('users', ['national_id' => 'A778899']);
    }

    public function test_leader_verifies_parent_and_links_are_approved(): void
    {
        $child = Student::factory()->create(['national_id' => 'A1000001']);
        $this->post('/register/parent', $this->payload(['A1000001']));
        $parent = User::query()->where('national_id', 'A778899')->firstOrFail();

        $this->actingAs($this->leader())->get('/parent-registrations')->assertOk()->assertSee('Fathimath Parent');
        $this->actingAs($this->leader())->post("/parent-registrations/{$parent->uuid}/verify")->assertSessionHas('success');

        $parent->refresh();
        $this->assertSame(UserStatus::Active, $parent->status);
        $this->assertSame(ParentLinkStatus::Approved, $parent->parentLinks()->first()->status);
        $this->assertSame([$child->id], $parent->approvedChildIds());
    }

    public function test_declined_parent_stays_inactive_and_links_are_rejected(): void
    {
        Student::factory()->create(['national_id' => 'A1000001']);
        $this->post('/register/parent', $this->payload(['A1000001']));
        $parent = User::query()->where('national_id', 'A778899')->firstOrFail();

        $this->actingAs($this->admin())->post("/parent-registrations/{$parent->uuid}/reject");

        $parent->refresh();
        $this->assertSame(UserStatus::Inactive, $parent->status);
        $this->assertNotNull($parent->verified_at);
        $this->assertSame(ParentLinkStatus::Rejected, $parent->parentLinks()->first()->status);
    }

    public function test_live_lookup_adds_chip_and_reports_messages(): void
    {
        $child = Student::factory()->create(['national_id' => 'A1000001', 'name' => 'Little Scout']);
        $taken = Student::factory()->create(['national_id' => 'A2000002']);
        $this->parentOf($taken);

        Livewire::test(ParentChildrenLookup::class)
            ->set('query', 'A100')
            ->assertSet('message', null)
            ->set('query', 'a1000001')
            ->assertSet('children.0.name', 'Little Scout')
            ->assertSet('query', '')
            ->set('query', 'A9999999')
            ->assertSet('message', 'No scout matches that National ID.')
            ->set('query', 'A2000002')
            ->assertSet('message', 'This scout is already added under another parent.')
            ->assertCount('children', 1);
    }
}
