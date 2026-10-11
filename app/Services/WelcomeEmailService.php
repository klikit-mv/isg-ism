<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails to scouts about their account. Sending never blocks or breaks registration.
 */
class WelcomeEmailService
{
    /**
     * A scout enrolled by staff: sign-in details including the PIN that was set.
     */
    public function enrolled(Student $student, string $pin): void
    {
        $this->send($student, 'Welcome to '.config('scout.name'), implode("\n", [
            "Hello {$student->name},",
            '',
            'You have been enrolled in '.config('scout.name').'. Here are your sign-in details:',
            '',
            "  Website:     ".url('/login'),
            "  National ID: {$student->national_id}",
            "  PIN:         {$pin}",
            '',
            'Please sign in and change your PIN under Profile. Keep it private.',
        ]));
    }

    /**
     * A scout who registered themselves: confirmation (their own PIN is never emailed).
     */
    public function registered(Student $student): void
    {
        $this->send($student, 'We received your registration', implode("\n", [
            "Hello {$student->name},",
            '',
            'Thank you for registering with '.config('scout.name').'. A leader will verify your details; you will get another email when you can sign in.',
            '',
            "  National ID: {$student->national_id}",
            '  PIN:         the PIN you chose when registering',
        ]));
    }

    /**
     * A leader verified the registration: the account now works.
     */
    public function approved(Student $student): void
    {
        $this->send($student, 'Your account is ready', implode("\n", [
            "Hello {$student->name},",
            '',
            'Your registration was verified. You can now sign in:',
            '',
            '  Website:     '.url('/login'),
            "  National ID: {$student->national_id}",
            '  PIN:         the PIN you chose when registering (use "Forgot your PIN?" if you have forgotten it)',
        ]));
    }

    private function send(Student $student, string $subject, string $body): void
    {
        if (blank($student->email)) {
            return;
        }

        try {
            Mail::raw($body."\n\n".config('scout.name'), fn ($message) => $message->to($student->email)->subject($subject));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
