<?php

namespace Tests\Feature\Ui;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_module_menu_is_a_row_of_cards_not_a_sidebar(): void
    {
        $this->actingAs($this->admin())
            ->get('/students')
            ->assertOk()
            ->assertSee('data-testid="menu-cards"', false)
            ->assertSee('data-testid="page-card"', false)
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
