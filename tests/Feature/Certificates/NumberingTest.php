<?php

namespace Tests\Feature\Certificates;

use App\Models\Badge;
use App\Services\CertificateNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NumberingTest extends TestCase
{
    use CertificateTestHelpers, RefreshDatabase;

    private function numbers(): CertificateNumberService
    {
        return app(CertificateNumberService::class);
    }

    public function test_proficiency_badges_of_a_section_share_one_sequence(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 1));
        $camping = $this->badge(['code' => 'CAMP']);
        $cooking = $this->badge(['code' => 'COOK', 'name' => 'Cooking']);
        $cubBadge = $this->badge(['code' => 'CUBX', 'section' => 'Cub Scout']);

        $this->assertSame('FLHSG-PB-2026-001', $this->numbers()->nextBadgeNumber($camping));
        $this->assertSame('FLHSG-PB-2026-002', $this->numbers()->nextBadgeNumber($cooking));
        $this->assertSame('FLHSG-PA-2026-001', $this->numbers()->nextBadgeNumber($cubBadge));
    }

    public function test_an_admin_sets_the_code_shared_by_a_sections_proficiency_badges(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 1));
        $camping = $this->badge(['code' => 'CAMP']);
        $cooking = $this->badge(['code' => 'COOK', 'name' => 'Cooking']);

        $this->actingAs($this->admin())->post(route('badges.section-codes'), ['codes' => ['Scout' => 'sct', 'Cub Scout' => '']])
            ->assertSessionHas('success');

        $this->assertSame('FLHSG-SCT-2026-001', $this->numbers()->nextBadgeNumber($camping));
        $this->assertSame('FLHSG-SCT-2026-002', $this->numbers()->nextBadgeNumber($cooking));
        $this->actingAs($this->admin())->get(route('badges.index'))->assertSee('data-testid="section-codes"', false)->assertSee('SCT');

        $this->actingAs($this->admin())->post(route('badges.section-codes'), ['codes' => ['Scout' => '']]);
        $this->assertSame('FLHSG-PB-2026-003', $this->numbers()->nextBadgeNumber($camping));
    }

    public function test_parents_cannot_change_section_codes(): void
    {
        $this->actingAs(\App\Models\User::factory()->parentRole()->create())->post(route('badges.section-codes'), ['codes' => ['Scout' => 'X']])->assertForbidden();
    }

    public function test_other_badges_have_their_own_sequence_with_prefix_or_code(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 1));
        $special = $this->badge(['code' => 'CHIEF', 'category' => 'special', 'section' => null]);
        $prefixed = $this->badge(['code' => 'JOTA', 'category' => 'event', 'section' => null, 'number_prefix' => 'RADIO']);

        $this->assertSame('FLHSG-CHIEF-2026-001', $this->numbers()->nextBadgeNumber($special));
        $this->assertSame('FLHSG-CHIEF-2026-002', $this->numbers()->nextBadgeNumber($special));
        $this->assertSame('FLHSG-RADIO-2026-001', $this->numbers()->nextBadgeNumber($prefixed));
    }

    public function test_sequences_reset_every_calendar_year(): void
    {
        $badge = $this->badge();

        $this->travelTo(now()->setDate(2026, 12, 31)->setTime(12, 0));
        $this->assertSame('FLHSG-PB-2026-001', $this->numbers()->nextBadgeNumber($badge));
        $this->assertSame('FLHSG-CERT-2026-001', $this->numbers()->nextGeneralNumber());

        $this->travelTo(now()->setDate(2027, 1, 2));
        $this->assertSame('FLHSG-PB-2027-001', $this->numbers()->nextBadgeNumber($badge));
        $this->assertSame('FLHSG-CERT-2027-001', $this->numbers()->nextGeneralNumber());
        $this->assertSame('FLHSG-LEAD-2027-001', $this->numbers()->nextLeadershipNumber());
    }

    public function test_year_follows_the_organisation_timezone(): void
    {
        // 20:00 UTC on 31 December is already 1 January in the Maldives (UTC+5).
        $this->travelTo(Carbon::parse('2026-12-31 20:00:00', 'UTC'));

        $this->assertSame('FLHSG-CERT-2027-001', $this->numbers()->nextGeneralNumber());
    }

    public function test_peek_does_not_consume_and_admin_can_set_next(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 1));

        $this->assertSame('FLHSG-LEAD-2026-001', $this->numbers()->peekLeadershipNumber());
        $this->assertSame('FLHSG-LEAD-2026-001', $this->numbers()->peekLeadershipNumber());

        $this->numbers()->setNextSequence('leadership:2026', 40);
        $this->assertSame('FLHSG-LEAD-2026-040', $this->numbers()->nextLeadershipNumber());
    }

    public function test_badge_next_number_can_be_set_from_the_badge_form(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 1));
        $admin = $this->admin();

        $this->actingAs($admin)->post('/badges', ['name' => 'Hiking', 'code' => 'hike', 'section' => 'Rover', 'next_number' => 15])->assertSessionHas('success');

        $badge = Badge::query()->where('code', 'HIKE')->firstOrFail();
        $this->assertSame('FLHSG-PD-2026-015', $this->numbers()->peekBadgeNumber($badge));
    }

    public function test_proficiency_badges_need_a_section_but_other_categories_do_not(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/badges', ['name' => 'Knots', 'code' => 'knot', 'category' => 'proficiency'])
            ->assertSessionHasErrors(['section' => 'Proficiency badges need a section: their certificate numbers continue in one sequence for that section all year.']);

        $this->actingAs($admin)->post('/badges', ['name' => 'Jamboree', 'code' => 'jam', 'category' => 'event'])->assertSessionHas('success');
        $this->assertNull(Badge::query()->where('code', 'JAM')->firstOrFail()->section);
    }
}
