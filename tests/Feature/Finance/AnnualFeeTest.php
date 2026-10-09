<?php

namespace Tests\Feature\Finance;

use App\Enums\Permission;
use App\Models\AnnualFee;
use App\Models\AnnualFeeYear;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_an_excel_list_is_matched_by_name_and_previewed_before_anything_is_created(): void
    {
        $admin = $this->admin();
        $year = AnnualFeeYear::query()->create(['year' => 2026, 'amount' => '100.00', 'status' => 'Active']);
        $aisha = Student::factory()->create(['name' => 'Aisha Ahmed']);
        $done = Student::factory()->create(['name' => 'Already Billed']);
        Student::factory()->create(['name' => 'Twin Name', 'national_id' => 'A111']);
        Student::factory()->create(['name' => 'Twin Name', 'national_id' => 'A222']);
        AnnualFee::query()->create(['annual_fee_year_id' => $year->id, 'student_id' => $done->id, 'person_type' => 'Student', 'amount' => '100.00', 'paid_amount' => '0.00', 'outstanding_amount' => '100.00', 'status' => 'Pending']);

        $csv = "Name,Section\n  aisha   AHMED ,Scout\nAlready Billed,\nNobody Here,\nTwin Name,\nAisha Ahmed,\n";
        $file = UploadedFile::fake()->createWithContent('list.csv', $csv);

        $response = $this->actingAs($admin)->post("/annual-fees/years/{$year->uuid}/import-preview", ['file' => $file])->assertOk();

        $response->assertSee('Will be invoiced (1)')->assertSee('Already invoiced for 2026')->assertSee('Nobody Here')->assertSee('More than one person has this name')->assertSee('Listed more than once');
        $response->assertSee('name="people[]" value="s'.$aisha->id.'"', false);
        $this->assertSame(1, AnnualFee::query()->count());

        $this->actingAs($admin)->post("/annual-fees/years/{$year->uuid}/generate", ['people' => ['s'.$aisha->id], 'sections' => ['s'.$aisha->id => 'Scout']])
            ->assertSessionHas('success', '1 invoice(s) created, 0 skipped (already invoiced).');
    }

    public function test_the_list_can_use_national_ids_and_needs_the_manage_fees_permission(): void
    {
        $year = AnnualFeeYear::query()->create(['year' => 2026, 'amount' => '100.00', 'status' => 'Active']);
        $student = Student::factory()->create(['name' => 'Real Name', 'national_id' => 'A987654']);
        $file = fn () => UploadedFile::fake()->createWithContent('list.csv', "Full Name,National ID\nSomeone Else,a987654\n");

        $this->actingAs($this->admin())->post("/annual-fees/years/{$year->uuid}/import-preview", ['file' => $file()])
            ->assertOk()->assertSee('Real Name')->assertSee('name="people[]" value="s'.$student->id.'"', false);
        $this->actingAs($this->leader())->post("/annual-fees/years/{$year->uuid}/import-preview", ['file' => $file()])->assertForbidden();
        $this->actingAs($this->admin())->post("/annual-fees/years/{$year->uuid}/import-preview", ['file' => UploadedFile::fake()->create('x.exe', 5)])->assertSessionHasErrors('file');
    }

    public function test_each_year_every_active_scout_can_be_invoiced_again_in_one_step(): void
    {
        $admin = $this->admin();
        $active = Student::factory()->count(3)->create();
        Student::factory()->inactive()->create();
        $leader = $this->leader();
        $this->actingAs($admin)->post('/annual-fees/years', ['year' => 2026, 'amount' => '100', 'invoice_everyone' => '1'])
            ->assertSessionHas('success', 'Fee year 2026 was created. 3 active scout(s) were invoiced.');
        $this->assertSame(3, AnnualFee::query()->whereNotNull('student_id')->count());
        $this->assertSame(0, AnnualFee::query()->whereNotNull('user_id')->count());

        $next = Student::factory()->create();
        $this->actingAs($admin)->post('/annual-fees/years', ['year' => 2027, 'amount' => '120', 'invoice_everyone' => '1'])
            ->assertSessionHas('success', 'Fee year 2027 was created. 4 active scout(s) were invoiced.');

        $this->assertSame(4, AnnualFee::query()->whereHas('feeYear', fn ($q) => $q->where('year', 2027))->count());
        $this->assertSame(3, AnnualFee::query()->whereHas('feeYear', fn ($q) => $q->where('year', 2026))->count());
        $this->assertSame('120.00', AnnualFee::query()->where('student_id', $next->id)->whereHas('feeYear', fn ($q) => $q->where('year', 2027))->firstOrFail()->amount);

        $late = Student::factory()->create();
        $year = AnnualFeeYear::query()->where('year', 2027)->firstOrFail();
        $this->actingAs($admin)->get('/annual-fees/years')->assertOk()->assertSee('Invoice all scouts');
        $this->actingAs($admin)->post("/annual-fees/years/{$year->uuid}/generate-all", ['include_leaders' => '1'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'invoice(s) created for 2027'));
        $this->assertTrue(AnnualFee::query()->where('student_id', $late->id)->whereHas('feeYear', fn ($q) => $q->where('year', 2027))->exists());
        $this->assertTrue(AnnualFee::query()->where('user_id', $leader->id)->whereHas('feeYear', fn ($q) => $q->where('year', 2027))->exists());
        $this->actingAs($this->parentOf($late))->post("/annual-fees/years/{$year->uuid}/generate-all")->assertForbidden();
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
