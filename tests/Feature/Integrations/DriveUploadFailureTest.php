<?php

namespace Tests\Feature\Integrations;

use App\Services\GoogleDrivePhotoService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriveUploadFailureTest extends TestCase
{
    use RefreshDatabase;

    private function connectDrive(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config(['services.google.service_account_json' => json_encode(['client_email' => 'bot@example.iam.gserviceaccount.com', 'private_key' => $pem])]);
        Cache::put('google.access_token.'.md5('bot@example.iam.gserviceaccount.com'), 'token', 600);
        $settings = app(SettingsService::class);
        $settings->set('google_drive_folder_id', 'root');
        $settings->set('google_drive_folder_students', 'studentsfolder');
    }

    public function test_a_refused_upload_keeps_the_photo_locally_and_explains_why(): void
    {
        Storage::fake('public');
        $this->connectDrive();
        Http::fake(['www.googleapis.com/upload/*' => Http::response(['error' => ['message' => 'Service Accounts do not have storage quota.', 'errors' => [['reason' => 'storageQuotaExceeded']]]], 403)]);

        $path = app(GoogleDrivePhotoService::class)->store(UploadedFile::fake()->image('a.jpg'), 'students');

        $this->assertStringStartsNotWith('drive:', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('no storage', session('warning'));
        $this->assertStringContainsString('not in Google Drive', session('warning'));
    }

    public function test_a_working_upload_is_stored_in_drive_without_a_warning(): void
    {
        Storage::fake('public');
        $this->connectDrive();
        Http::fake(['www.googleapis.com/upload/*' => Http::response(['id' => 'file123'])]);

        $path = app(GoogleDrivePhotoService::class)->store(UploadedFile::fake()->image('a.jpg'), 'students');

        $this->assertSame('drive:file123', $path);
        $this->assertNull(session('warning'));
    }
}
