<?php

namespace Tests\Feature\Operations;

use App\Enums\ScoutSection;
use App\Models\AuditLog;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_promotes_selected_scouts_one_section_forward(): void
    {
        $cubs = Student::factory()->count(2)->section(ScoutSection::CubScout)->create();
        $alreadyScout = Student::factory()->create();

        $this->actingAs($this->admin())->post('/students/promote', [
            'from' => 'Cub Scout',
            'to' => 'Scout',
            'students' => [$cubs[0]->id, $cubs[1]->id, $alreadyScout->id],
        ])->assertSessionHas('success', '2 scout(s) promoted to Scout; 1 skipped.');

        $this->assertSame(ScoutSection::Scout, $cubs[0]->fresh()->section);
        $this->assertSame(2, AuditLog::query()->where('action', 'student.promoted')->count());
    }

    public function test_skipping_or_reversing_sections_is_rejected(): void
    {
        $cub = Student::factory()->section(ScoutSection::CubScout)->create();

        $this->actingAs($this->admin())->post('/students/promote', ['from' => 'Cub Scout', 'to' => 'Rover', 'students' => [$cub->id]])
            ->assertSessionHas('error');
        $this->actingAs($this->admin())->post('/students/promote', ['from' => 'Cub Scout', 'to' => 'Pre Cub', 'students' => [$cub->id]])
            ->assertSessionHas('error');

        $this->assertSame(ScoutSection::CubScout, $cub->fresh()->section);
    }

    public function test_only_admins_can_promote(): void
    {
        $cub = Student::factory()->section(ScoutSection::CubScout)->create();

        $this->actingAs($this->leader())->get('/students/promote')->assertForbidden();
        $this->actingAs($this->leader())->post('/students/promote', ['from' => 'Cub Scout', 'to' => 'Scout', 'students' => [$cub->id]])->assertForbidden();
    }
}
