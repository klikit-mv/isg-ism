<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_student_photos_are_served_through_the_app_to_signed_in_users(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create();
        $this->actingAs($admin)->post("/students/{$student->uuid}/photo", ['photo' => UploadedFile::fake()->image('me.jpg')]);

        $url = photo_url($student->fresh()->photo_path);
        $this->assertStringStartsWith('/media/students/', $url);

        $this->actingAs($admin)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_only_image_folders_can_be_read(): void
    {
        Storage::disk('public')->put('branding/secret.txt', 'nope');
        Storage::disk('public')->put('students/note.txt', 'text');
        $user = $this->parentOf();

        $this->actingAs($user)->get('/media/branding/secret.txt')->assertNotFound();
        $this->actingAs($user)->get('/media/students/../branding/secret.txt')->assertNotFound();
        $this->actingAs($user)->get('/media/students/note.txt')->assertNotFound();
        $this->actingAs($user)->get('/media/students/missing.png')->assertNotFound();
    }
}
