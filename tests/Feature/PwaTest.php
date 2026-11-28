<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_makes_the_portal_installable(): void
    {
        $response = $this->get('/manifest.webmanifest')->assertOk();

        $this->assertSame('standalone', $response->json('display'));
        $this->assertSame('/dashboard', $response->json('start_url'));
        $this->assertCount(3, $response->json('icons'));
        $this->get('/')->assertSee('rel="manifest"', false)->assertSee('apple-touch-icon', false)->assertSee('serviceWorker', false);
    }

    public function test_app_icons_are_png_images_in_the_allowed_sizes_only(): void
    {
        $response = $this->get('/pwa-icon/192.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame([192, 192], array_slice(getimagesizefromstring($response->getContent()), 0, 2));
        $this->get('/pwa-icon/999.png')->assertNotFound();
    }

    public function test_the_offline_page_and_service_worker_exist(): void
    {
        $this->get('/offline')->assertOk()->assertSee('You are offline');
        $this->assertFileExists(public_path('sw.js'));
    }
}
