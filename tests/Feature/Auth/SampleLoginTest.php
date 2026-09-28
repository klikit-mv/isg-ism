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

    public function test_sample_logins_are_listed_only_in_the_local_environment(): void
    {
        $this->app['env'] = 'local';
        $this->get('/login')->assertOk()->assertSee('data-testid="sample-logins"', false)->assertSee('A000001');

        $this->app['env'] = 'production';
        $this->get('/login')->assertOk()->assertDontSee('data-testid="sample-logins"', false);
    }
}
