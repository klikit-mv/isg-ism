<?php

namespace Tests\Feature\Finance;

use App\Models\Activity;
use App\Models\ClassFee;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentDriveTest extends TestCase
{
    use RefreshDatabase;

    private function connect(): void
    {
        Storage::fake('local');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config(['services.google.service_account_json' => json_encode(['client_email' => 'bot@example.iam.gserviceaccount.com', 'private_key' => $pem])]);
        Cache::put('google.access_token.'.md5('bot@example.iam.gserviceaccount.com'), 'token', 600);
        app(SettingsService::class)->set('google_drive_payments_folder_id', 'payroot');
        app(SettingsService::class)->set('google_drive_payments_class_fee', 'classfolder');
    }

    private function submit(): Student
    {
        $student = Student::factory()->create();
        $activity = Activity::factory()->forAll()->charged('20.00')->create();
        app(AttendanceService::class)->mark($activity, User::factory()->admin()->create(), [$student->id => ['status' => 'Present']]);
        $fee = ClassFee::query()->where('student_id', $student->id)->firstOrFail();
        $parent = User::factory()->parentRole()->create();
        $student->parentLinks()->create(['parent_user_id' => $parent->id, 'status' => 'approved']);

        $this->actingAs($parent)->post('/payments', [
            'payable_type' => 'class_fee', 'payable_id' => $fee->uuid, 'amount' => '20.00', 'method' => 'online',
            'proof' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ]);

        return $student;
    }

    public function test_a_proof_is_filed_in_the_modules_drive_folder_and_the_local_copy_is_removed(): void
    {
        $this->connect();
        Http::fake([
            'www.googleapis.com/upload/*' => Http::response(['id' => 'proof123']),
            'www.googleapis.com/drive/v3/files/proof123*' => Http::response('%PDF stored'),
            'www.googleapis.com/drive/v3/files*' => fn ($request) => $request->method() === 'GET' ? Http::response(['files' => [['id' => 'classfolder']]]) : Http::response(['id' => 'classfolder']),
        ]);

        $this->submit();

        $proof = PaymentProof::query()->firstOrFail();
        $this->assertSame('drive', $proof->disk);
        $this->assertSame('drive:proof123', $proof->path);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'upload/drive'));
        $this->assertSame([], Storage::disk('local')->allFiles('payment-proofs'));

        $this->actingAs($this->admin())->get(route('payments.proof', $proof->payment))->assertOk()->assertSee('%PDF stored', false);
    }

    public function test_when_drive_refuses_the_proof_stays_on_the_server_with_a_warning(): void
    {
        $this->connect();
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['message' => 'no', 'errors' => [['reason' => 'storageQuotaExceeded']]]], 403)]);

        $this->submit();

        $proof = PaymentProof::query()->firstOrFail();
        $this->assertSame('local', $proof->disk);
        Storage::disk('local')->assertExists($proof->path);
        $this->assertStringContainsString('not in Google Drive', session('warning'));
    }

    public function test_a_finance_folder_is_created_inside_the_main_drive_folder_when_none_is_set(): void
    {
        $this->connect();
        $settings = app(SettingsService::class);
        $settings->set('google_drive_payments_folder_id', null);
        $settings->set('google_drive_payments_class_fee', null);
        $settings->set('google_drive_folder_id', 'mainroot');
        Http::fake([
            'www.googleapis.com/drive/v3/files*' => fn ($request) => $request->method() === 'GET'
                ? Http::response(['files' => []])
                : Http::response(['id' => str_contains($request->body(), '"Finance"') ? 'financeId' : 'classId']),
            'www.googleapis.com/upload/*' => Http::response(['id' => 'proof1']),
        ]);

        $this->submit();

        $this->assertSame('financeId', $settings->drivePaymentsFolderId());
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->body(), '"name":"Finance"') && str_contains($r->body(), '"parents":["mainroot"]'));
        $this->assertSame('drive', PaymentProof::query()->firstOrFail()->disk);
    }

    public function test_the_proof_goes_into_a_folder_for_the_scout(): void
    {
        $this->connect();
        app(SettingsService::class)->set('google_drive_payments_class_fee', 'classfolder');
        Http::fake([
            'www.googleapis.com/drive/v3/files*' => fn ($request) => $request->method() === 'GET'
                ? Http::response(['files' => []])
                : Http::response(['id' => 'scoutFolder']),
            'www.googleapis.com/upload/*' => Http::response(['id' => 'proof1']),
        ]);

        $student = $this->submit();

        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->body(), '"name":'.json_encode($student->name.' ('.$student->national_id.')')) && str_contains($r->body(), '"parents":["classfolder"]'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'upload/drive') && str_contains($r->body(), '"parents":["scoutFolder"]'));
    }

    public function test_the_settings_page_shows_the_finance_folder_input(): void
    {
        $html = $this->actingAs($this->admin())->get('/settings')->assertOk()->getContent();

        $this->assertStringContainsString('name="google_drive_payments_folder"', $html);
        $this->assertStringNotContainsString('<x-form', $html);
    }
}
