<?php

namespace Tests\Feature\Ui;

use App\Models\Badge;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_clicking_a_heading_orders_the_whole_list_by_that_column(): void
    {
        $admin = $this->admin();
        Student::factory()->create(['name' => 'Bravo Scout', 'national_id' => 'A300']);
        Student::factory()->create(['name' => 'Alpha Scout', 'national_id' => 'A200']);
        Student::factory()->create(['name' => 'Charlie Scout', 'national_id' => 'A100']);

        $this->actingAs($admin)->get('/students?sort=name&dir=asc')->assertOk()->assertSeeInOrder(['Alpha Scout', 'Bravo Scout', 'Charlie Scout']);
        $this->actingAs($admin)->get('/students?sort=name&dir=desc')->assertSeeInOrder(['Charlie Scout', 'Bravo Scout', 'Alpha Scout']);
        $this->actingAs($admin)->get('/students?sort=national_id&dir=asc')->assertSeeInOrder(['Charlie Scout', 'Alpha Scout', 'Bravo Scout']);
    }

    public function test_headings_link_to_the_sorted_page_and_flip_the_direction(): void
    {
        $admin = $this->admin();
        Student::factory()->create(['name' => 'Xavier Scout']);

        $html = $this->actingAs($admin)->get('/students?q=x&sort=name&dir=asc')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/href="[^"]*sort=name&amp;dir=desc"/', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*sort=national_id&amp;dir=asc"/', $html);
        $this->assertStringContainsString('q=x', $html);
        $this->assertStringContainsString('aria-sort="ascending"', $html);
    }

    public function test_an_unknown_sort_column_or_direction_is_ignored_safely(): void
    {
        $admin = $this->admin();
        Student::factory()->count(2)->create();

        $this->actingAs($admin)->get('/students?sort=password;drop%20table&dir=sideways')->assertOk();
        $this->actingAs($admin)->get('/users?sort=password&dir=desc')->assertOk();
        $this->actingAs($admin)->get('/payments?sort=zzz')->assertOk();
    }

    public function test_every_sortable_list_page_still_loads_with_each_of_its_headings(): void
    {
        $admin = $this->admin();

        foreach ([
            '/students' => ['name', 'index', 'status'], '/users' => ['name', 'email'], '/payments' => ['submitted', 'scout', 'verifier'],
            '/class-fees' => ['scout', 'activity', 'due'], '/annual-fees' => ['year', 'person', 'section'], '/certificates' => ['number', 'awarded'],
            '/badge-requests' => ['request', 'requested'], '/audit-logs' => ['when', 'by'], '/activities' => ['date', 'marked'], '/groups' => ['members', 'status'],
            '/leadership' => ['scout', 'start'], '/badges' => ['name', 'template'], '/events/registrations' => ['event', 'participant', 'total'], '/purchases' => ['date', 'scout', 'order'],
        ] as $url => $keys) {
            foreach ($keys as $key) {
                foreach (['asc', 'desc'] as $dir) {
                    $this->actingAs($admin)->get("{$url}?sort={$key}&dir={$dir}")->assertOk();
                }
            }
        }
    }

    public function test_long_dropdowns_are_searchable_everywhere_including_the_bulk_badge_window(): void
    {
        $leader = $this->leader();
        foreach (range(1, 12) as $n) {
            Badge::query()->create(['badge_id' => "B{$n}", 'name' => "Badge {$n}", 'code' => "C{$n}", 'section' => 'Scout', 'category' => 'proficiency']);
        }

        $html = $this->actingAs($leader)->get('/badge-requests')->assertOk()->getContent();

        $this->assertStringContainsString('Type to search', $html);
        $this->assertStringNotContainsString('<select id="bulk-badge"', $html);
    }
}
