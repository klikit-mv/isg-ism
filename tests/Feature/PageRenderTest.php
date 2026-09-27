<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ScoutAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every screen renders for the people who can open it.
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_render(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create();
        $this->parentOf($student);
        $group = Group::factory()->withMembers($student)->create();
        $user = User::factory()->leader()->create();
        $admin->notify(new ScoutAlert('Hello', 'World', url('/dashboard')));
        $notification = $admin->notifications()->first();

        $pages = [
            '/dashboard', '/profile', '/notifications', "/notifications/{$notification->id}",
            '/users', '/users/create', "/users/{$user->uuid}/edit", '/parent-links',
            '/students', '/students/create', "/students/{$student->uuid}", "/students/{$student->uuid}/edit",
            "/students/{$student->uuid}/certificates", "/students/{$student->uuid}/badge-requests", "/students/{$student->uuid}/leadership",
            '/students/promote', '/students/promote?from=Scout', '/parent-registrations', '/groups', "/groups/{$group->uuid}",
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }

    public function test_family_and_self_pages_render(): void
    {
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $scout = $this->studentUser($student);

        foreach (['/family', '/family/attendance', "/family/students/{$student->uuid}", "/family/students/{$student->uuid}/certificates"] as $page) {
            $this->actingAs($parent)->get($page)->assertOk();
        }

        foreach (['/me', '/me/certificates', '/me/badge-requests', '/me/leadership', '/me/attendance'] as $page) {
            $this->actingAs($scout)->get($page)->assertOk();
        }
    }

    public function test_guest_pages_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/register/parent')->assertOk();
    }
}
