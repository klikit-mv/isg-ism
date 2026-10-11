<?php

namespace Tests\Feature\Registration;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $over = []): array
    {
        return array_merge([
            'index_number' => 'IX900', 'national_id' => 'a900900', 'name' => 'Aishath Test', 'email' => 'aishath@example.com', 'gender' => 'Female',
            'permanent_address' => 'Male', 'present_address' => 'Male', 'date_of_birth' => '2015-03-15', 'parent_name' => 'Parent', 'primary_mobile' => '7770000',
            'section' => 'Cub Scout', 'status' => 'active', 'pin' => '4321',
        ], $over);
    }

    private function capture(): \ArrayObject
    {
        $sent = new \ArrayObject;
        Mail::shouldReceive('raw')->andReturnUsing(function ($body, $callback) use ($sent) {
            $message = new class
            {
                public array $to = [];

                public string $subject = '';

                public function to($address) { $this->to[] = $address; return $this; }

                public function subject($subject) { $this->subject = $subject; return $this; }
            };
            $callback($message);
            $sent[] = ['body' => $body, 'to' => $message->to[0], 'subject' => $message->subject];
        });

        return $sent;
    }

    public function test_an_enrolled_scout_gets_their_sign_in_details(): void
    {
        $sent = $this->capture();
        $this->actingAs($this->admin())->post('/students', $this->payload())->assertSessionHasNoErrors();

        $this->assertCount(1, $sent);
        $this->assertSame('aishath@example.com', $sent[0]['to']);
        $this->assertStringContainsString('A900900', $sent[0]['body']);
        $this->assertStringContainsString('4321', $sent[0]['body']);
    }

    public function test_the_temporary_pin_is_emailed_when_none_was_typed(): void
    {
        $sent = $this->capture();
        $this->actingAs($this->admin())->post('/students', $this->payload(['pin' => '']))->assertSessionHasNoErrors();

        $pin = \App\Models\User::query()->where('national_id', 'A900900')->firstOrFail();
        preg_match('/PIN:\s+(\d{4})/', $sent[0]['body'], $m);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($m[1], $pin->password));
    }

    public function test_self_registration_and_approval_send_emails_without_the_chosen_pin(): void
    {
        $sent = $this->capture();
        $this->post('/register', $this->payload(['pin' => '5555', 'pin_confirmation' => '5555']))->assertSessionHasNoErrors();
        $this->assertCount(1, $sent);
        $this->assertStringNotContainsString('5555', $sent[0]['body']);

        $student = Student::query()->where('national_id', 'A900900')->firstOrFail();
        $this->assertSame(StudentStatus::Pending, $student->status);
        $this->actingAs($this->leader())->post("/students/{$student->uuid}/verify")->assertSessionHas('success');

        $this->assertCount(2, $sent);
        $this->assertSame('Your account is ready', $sent[1]['subject']);
        $this->assertStringNotContainsString('5555', $sent[1]['body']);
    }

    public function test_a_mail_failure_does_not_break_enrolment(): void
    {
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('smtp down'));
        $this->actingAs($this->admin())->post('/students', $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students', ['national_id' => 'A900900']);
    }

    public function test_student_email_can_be_made_optional_from_settings(): void
    {
        $admin = $this->admin();
        $sent = $this->capture();

        $this->actingAs($admin)->post('/students', $this->payload(['email' => '']))->assertSessionHasErrors('email');

        $this->actingAs($admin)->post('/settings', ['default_class_fee' => '50', 'proof_max_kb' => 10240, 'student_email_required' => '0'])->assertSessionHas('success');
        $this->assertFalse(app(SettingsService::class)->studentEmailRequired());

        $this->actingAs($admin)->post('/students', $this->payload(['email' => '']))->assertSessionHasNoErrors();
        $this->assertCount(0, $sent, 'no address, no email');
        $this->assertDatabaseHas('students', ['national_id' => 'A900900', 'email' => null]);

        $this->actingAs($admin)->get('/students/create')->assertSee('Email (optional)');
    }
}
