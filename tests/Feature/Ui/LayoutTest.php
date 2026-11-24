<?php

namespace Tests\Feature\Ui;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_entering_a_module_shows_only_its_sub_cards_and_each_page_opens_on_its_own(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/modules/operations')
            ->assertOk()
            ->assertSee('data-testid="menu-cards"', false)
            ->assertSee('Mark attendance')
            ->assertDontSee('Enrol scout');

        $this->actingAs($admin)
            ->get('/students')
            ->assertOk()
            ->assertSee('data-testid="back-to-menu"', false)
            ->assertDontSee('data-testid="menu-cards"', false)
            ->assertDontSee('<aside', false);
    }

    public function test_pages_recover_from_broken_assets_and_the_service_worker_never_caches_bad_responses(): void
    {
        $this->get('/')
            ->assertSee('scoutAssetsFailed', false);

        $worker = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString('isGood', $worker);
        $this->assertStringContainsString('scout-shell-v3', $worker);
    }

    public function test_the_home_page_shows_only_the_main_module_cards(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-module="operations"', false)
            ->assertDontSee('data-testid="module-pages"', false);
    }
}
