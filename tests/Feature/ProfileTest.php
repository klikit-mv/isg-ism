<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_profile_shows_only_the_personal_area(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/modules/administration');

        $this->actingAs($admin)->get('/profile')
            ->assertOk()
            ->assertSee('My account')
            ->assertSee('My profile')
            ->assertSee(route('notifications.index'), false)
            ->assertDontSee(route('users.index'), false)
            ->assertDontSee('Audit logs');
    }

    public function test_user_uploads_a_profile_picture_shown_in_the_header(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg', 300, 300)])
            ->assertSessionHas('success', 'Your profile picture was updated.');

        $path = $user->fresh()->avatar_path;
        $this->assertStringStartsWith('avatars/', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)->get('/dashboard')->assertSee('data-testid="user-avatar"', false)->assertSee('/media/'.$path, false);
        $this->actingAs($user)->get('/media/'.$path)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_replacing_and_removing_the_picture_cleans_up_files(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('one.png')]);
        $first = $user->fresh()->avatar_path;

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('two.png')]);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($user)->post('/profile/avatar', ['remove' => '1'])->assertSessionHas('success');
        $this->assertNull($user->fresh()->avatar_path);
        $this->actingAs($user)->get('/dashboard')->assertDontSee('data-testid="user-avatar"', false);
    }

    public function test_only_images_are_accepted_as_profile_pictures(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('me.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');
        $this->actingAs($user)->post('/profile/avatar', [])->assertSessionHasErrors(['avatar' => 'Choose a picture to upload.']);

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_scouts_without_a_picture_show_their_scout_photo(): void
    {
        $scout = $this->studentUser();
        $scout->student->update(['photo_path' => 'students/photo.jpg']);

        $this->assertSame('/media/students/photo.jpg', $scout->fresh()->avatarUrl());
    }
}
