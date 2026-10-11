<?php

namespace Tests\Feature\Auth;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SampleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('certificates');
        $this->seed(DemoSeeder::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sampleNationalIds(): array
    {
        return [
            'admin' => ['A000001'],
            'leader' => ['A100001'],
            'treasurer' => ['A100002'],
            'parent' => ['A100003'],
            'scout' => ['A200001'],
        ];
    }

    #[DataProvider('sampleNationalIds')]
    public function test_every_sample_account_signs_in_with_the_sample_pin(string $nationalId): void
    {
        $this->post('/login', ['national_id' => $nationalId, 'pin' => DemoSeeder::samplePin()])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_sample_admin_opens_administration(): void
    {
        $this->post('/login', ['national_id' => 'A000001', 'pin' => DemoSeeder::samplePin()]);

        $this->get('/dashboard')->assertSee('data-module="administration"', false);
    }

    public function test_the_sign_in_page_never_lists_sample_logins(): void
    {
        foreach (['local', 'production'] as $environment) {
            $this->app['env'] = $environment;
            $this->get('/login')->assertOk()->assertDontSee('data-testid="sample-logins"', false)->assertDontSee('A000001')->assertDontSee('Sample logins');
        }
    }
}
