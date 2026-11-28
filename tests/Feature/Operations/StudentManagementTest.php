<?php

namespace Tests\Feature\Operations;

use App\Enums\StudentStatus;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'index_number' => 'IX2001',
            'national_id' => 'A556677',
            'name' => 'Enrolled Scout',
            'email' => 'enrolled@example.com',
            'gender' => 'Female',
            'permanent_address' => 'Addu',
            'present_address' => 'Male',
            'date_of_birth' => '2013-01-01',
            'parent_name' => 'Parent',
            'primary_mobile' => '7654321',
            'section' => 'Cub Scout',
            'status' => 'active',
        ], $overrides);
    }

    public function test_admin_enrols_scout_with_account_and_temporary_pin(): void
    {
        $this->actingAs($this->admin())->post('/students', $this->payload())->assertSessionHas('success');

        $student = Student::query()->where('national_id', 'A556677')->firstOrFail();
        $this->assertSame(UserStatus::Active, $student->user->status);
        $this->assertTrue($student->user->hasRole('student'));
        $this->assertMatchesRegularExpression('/temporary PIN is \d{4}/', session('success'));
    }

    public function test_admin_enrols_with_a_chosen_pin(): void
    {
        $this->actingAs($this->admin())->post('/students', $this->payload(['pin' => '8642']));

        $this->assertTrue(Hash::check('8642', Student::query()->firstOrFail()->user->password));
    }

    public function test_edit_mirrors_details_onto_the_account(): void
    {
        $student = Student::factory()->create();
        $user = $this->studentUser($student);

        $this->actingAs($this->admin())->put("/students/{$student->uuid}", $this->payload([
            'index_number' => $student->index_number,
            'national_id' => 'B111222',
            'email' => 'new@example.com',
            'name' => 'New Name',
        ]))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('B111222', $user->national_id);
        $this->assertSame('new@example.com', $user->email);
    }

    public function test_changing_status_with_a_reason_mirrors_onto_the_account(): void
    {
        $student = Student::factory()->create();
        $user = $this->studentUser($student);

        $this->actingAs($this->admin())->post(route('students.status', $student), ['status' => 'inactive', 'reason' => 'Left the group', 'effective_date' => scout_today()])->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Inactive, $user->refresh()->status);
    }

    public function test_delete_soft_deletes_student_and_account(): void
    {
        $student = Student::factory()->create();
        $user = $this->studentUser($student);

        $this->actingAs($this->admin())->delete("/students/{$student->uuid}");

        $this->assertSoftDeleted($student);
        $this->assertSoftDeleted($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.deleted']);
    }

    public function test_a_leader_can_enrol_a_scout_but_not_edit_or_delete(): void
    {
        $leader = $this->leader();
        $student = Student::factory()->create();
        Group::factory()->ledBy($leader)->withMembers($student)->create();

        $this->actingAs($leader)->get('/students/create')->assertOk();
        $this->actingAs($leader)->post('/students', $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students', ['national_id' => 'A556677']);
        $this->actingAs($leader)->put("/students/{$student->uuid}", $this->payload())->assertForbidden();
        $this->actingAs($leader)->delete("/students/{$student->uuid}")->assertForbidden();
    }

    public function test_scoped_leader_sets_a_photo_but_not_outside_scope(): void
    {
        Storage::fake('public');
        $leader = $this->leader();
        $mine = Student::factory()->create();
        $other = Student::factory()->create();
        Group::factory()->ledBy($leader)->withMembers($mine)->create();

        $this->actingAs($leader)->post("/students/{$mine->uuid}/photo", ['photo' => UploadedFile::fake()->image('me.jpg')])
            ->assertSessionHas('success');
        $this->assertNotNull($mine->fresh()->photo_path);
        Storage::disk('public')->assertExists($mine->fresh()->photo_path);

        $this->actingAs($leader)->post("/students/{$other->uuid}/photo", ['photo' => UploadedFile::fake()->image('x.jpg')])->assertForbidden();
    }

    public function test_list_puts_pending_registrations_first(): void
    {
        Student::factory()->create(['name' => 'Aaron Active']);
        Student::factory()->pending()->create(['name' => 'Zed Pending']);

        $this->actingAs($this->admin())->get('/students')->assertSeeInOrder(['Zed Pending', 'Aaron Active']);
    }

    public function test_status_filter(): void
    {
        Student::factory()->create(['name' => 'Aaron Active']);
        Student::factory()->inactive()->create(['name' => 'Ina Inactive']);

        $this->actingAs($this->admin())->get('/students?status='.StudentStatus::Inactive->value)
            ->assertSee('Ina Inactive')->assertDontSee('Aaron Active');
    }

    public function test_students_can_be_filtered_to_those_not_in_any_group(): void
    {
        $grouped = Student::factory()->create(['name' => 'Grouped Scout']);
        $alone = Student::factory()->create(['name' => 'Ungrouped Scout']);
        Group::factory()->create()->members()->attach($grouped->id);

        $this->actingAs($this->admin())->get('/students?group=none')
            ->assertOk()
            ->assertSee('Ungrouped Scout')
            ->assertDontSee('Grouped Scout');

        $this->actingAs($this->admin())->get('/students')->assertSee('Grouped Scout');
    }

    public function test_admin_and_a_scoped_leader_can_reset_a_scouts_pin_but_not_other_leaders_scouts(): void
    {
        $leader = $this->leader();
        $mine = Student::factory()->create();
        $other = Student::factory()->create();
        Group::factory()->ledBy($leader)->withMembers($mine)->create();
        $mineUser = $this->studentUser($mine);
        $otherUser = $this->studentUser($other);

        $this->actingAs($leader)->get(route('students.show', $mine))->assertSee('data-testid="reset-pin"', false);
        $this->actingAs($leader)->post(route('students.pin', $mine), ['pin' => '4321'])->assertSessionHas('success');
        $this->assertTrue(Hash::check('4321', $mineUser->fresh()->password));

        $this->actingAs($leader)->post(route('students.pin', $other))->assertForbidden();

        $this->actingAs($this->admin())->post(route('students.pin', $other))->assertSessionHas('success');
        $this->assertFalse(Hash::check('x', $otherUser->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.pin_reset']);
    }
}
