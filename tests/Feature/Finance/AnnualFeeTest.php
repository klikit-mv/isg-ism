<?php

namespace Tests\Feature\Finance;

use App\Enums\Permission;
use App\Models\AnnualFee;
use App\Models\AnnualFeeYear;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_fee_year(): void
    {
        $this->actingAs($this->admin())->post('/annual-fees/years', ['year' => 2026, 'amount' => '100'])->assertSessionHas('success');

        $this->assertDatabaseHas('annual_fee_years', ['year' => 2026, 'amount' => '100.00', 'status' => 'Active']);
    }

    public function test_generation_is_idempotent_and_reports_counts(): void
    {
        $admin = $this->admin();
        $year = AnnualFeeYear::query()->create(['year' => 2026, 'amount' => '100.00', 'status' => 'Active']);
        [$a, $b] = Student::factory()->count(2)->create();
        $leader = $this->leader();

        $this->actingAs($admin)->post("/annual-fees/years/{$year->uuid}/generate", ['people' => ['s'.$a->id, 'u'.$leader->id]])
            ->assertSessionHas('success', '2 invoice(s) created, 0 skipped (already invoiced).');
        $this->actingAs($admin)->post("/annual-fees/years/{$year->uuid}/generate", ['people' => ['s'.$a->id, 's'.$b->id, 'u'.$leader->id]])
            ->assertSessionHas('success', '1 invoice(s) created, 2 skipped (already invoiced).');

        $this->assertSame(3, AnnualFee::query()->count());
        $this->assertSame('Leader', AnnualFee::query()->where('user_id', $leader->id)->firstOrFail()->person_type->value);
    }

    public function test_back_dated_section_is_stored_without_changing_the_scout(): void
    {
        $year = AnnualFeeYear::query()->create(['year' => 2025, 'amount' => '80.00', 'status' => 'Active']);
        $scout = Student::factory()->create();

        $this->actingAs($this->admin())->post("/annual-fees/years/{$year->uuid}/generate", [
            'people' => ['s'.$scout->id],
            'sections' => ['s'.$scout->id => 'Cub Scout'],
        ]);

        $this->assertSame('Cub Scout', AnnualFee::query()->firstOrFail()->section->value);
        $this->assertSame('Scout', $scout->fresh()->section->value);
    }

    public function test_inactive_year_cannot_generate(): void
    {
        $year = AnnualFeeYear::query()->create(['year' => 2026, 'amount' => '100.00', 'status' => 'Inactive']);
        $scout = Student::factory()->create();

        $this->actingAs($this->admin())->post("/annual-fees/years/{$year->uuid}/generate", ['people' => ['s'.$scout->id]])
            ->assertSessionHas('error', 'Only Active annual fee years can have fees generated.');
        $this->assertSame(0, AnnualFee::query()->count());
    }

    public function test_manage_fees_permission_generates_but_cannot_create_years(): void
    {
        $manager = $this->leader(Permission::ManageFees);
        $year = AnnualFeeYear::query()->create(['year' => 2026, 'amount' => '100.00', 'status' => 'Active']);
        $scout = Student::factory()->create();

        $this->actingAs($manager)->get('/annual-fees/years')->assertOk();
        $this->actingAs($manager)->post("/annual-fees/years/{$year->uuid}/generate", ['people' => ['s'.$scout->id]])->assertSessionHas('success');
        $this->actingAs($manager)->post('/annual-fees/years', ['year' => 2027, 'amount' => '1'])->assertForbidden();
        $this->actingAs($manager)->post("/annual-fees/years/{$year->uuid}/status", ['status' => 'Inactive'])->assertForbidden();
        $this->actingAs($this->leader())->get('/annual-fees/years')->assertForbidden();
    }

    public function test_leader_sees_own_leader_fee(): void
    {
        $leader = $this->leader();
        $year = AnnualFeeYear::query()->create(['year' => 2026, 'amount' => '100.00', 'status' => 'Active']);
        AnnualFee::query()->create(['annual_fee_year_id' => $year->id, 'user_id' => $leader->id, 'person_type' => 'Leader', 'amount' => '100.00', 'outstanding_amount' => '100.00', 'status' => 'Pending']);

        $this->actingAs($leader)->get('/annual-fees')->assertSee($leader->name);
    }
}
