<?php

namespace App\Services;

use App\Enums\ParentLinkStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\ParentStudentLink;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ScoutAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decides who hears about what.
 */
class NotificationService
{
    public function send(?User $user, string $title, string $body, ?string $url = null): void
    {
        if ($user === null) {
            return;
        }

        try {
            $user->notify(new ScoutAlert($title, $body, $url));
        } catch (Throwable $e) {
            Log::warning('Notification failed', ['user' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  iterable<User>  $users
     */
    public function sendMany(iterable $users, string $title, string $body, ?string $url = null): void
    {
        foreach ($users as $user) {
            $this->send($user, $title, $body, $url);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function staff(): Collection
    {
        return User::query()->active()
            ->whereHas('roleRows', fn ($q) => $q->whereIn('role', [Role::Admin->value, Role::Leader->value]))
            ->get();
    }

    public function studentRegistered(Student $student): void
    {
        $this->sendMany($this->staff(), 'New scout registration', "{$student->name} ({$student->section?->value}) registered and is waiting for verification.", route('students.show', $student));
    }

    public function parentRegistered(User $parent): void
    {
        $this->sendMany($this->staff(), 'New parent registration', "{$parent->name} registered as a parent and is waiting for verification.", route('parent-registrations.index'));
    }

    public function parentVerified(User $parent): void
    {
        $this->send($parent, 'Your parent account is verified', 'A leader verified your registration. You can now sign in and see your children.', route('family.index'));
    }

    public function studentVerified(Student $student): void
    {
        $this->send($student->user, 'Your registration is verified', 'A leader verified your registration. Welcome to '.config('scout.name').'!', route('self.show'));
    }

    public function paymentSubmitted(Payment $payment): void
    {
        $verifiers = User::query()->active()->withPermission(Permission::VerifyPayments)->get();
        $name = $payment->student?->name ?? $payment->submitter?->name ?? 'a member';

        $this->sendMany($verifiers, 'Payment waiting for verification', 'An online payment of '.scout_money($payment->amount)." for {$name} needs checking.", route('payment-verification.index'));
    }

    public function paymentDecided(Payment $payment): void
    {
        $approved = $payment->status->value === 'Paid';
        $body = $approved
            ? 'Your payment of '.scout_money($payment->amount).' was approved.'
            : 'Your payment of '.scout_money($payment->amount).' was rejected. Reason: '.$payment->rejection_reason;

        $this->send($payment->submitter, $approved ? 'Payment approved' : 'Payment rejected', $body, route('payments.index'));
    }

    /**
     * Notify roster scouts and their approved parents, never the creator and
     * never twice for a parent who is also on the roster.
     *
     * @param  list<int>  $studentIds
     */
    public function activityCreated(Activity $activity, array $studentIds, ?User $creator): void
    {
        $body = "You are expected to attend {$activity->name} on ".scout_date($activity->date).'.';
        $url = route('dashboard');

        $recipients = User::query()->active()->whereIn('student_id', $studentIds)->get()->keyBy('id');

        $parentIds = ParentStudentLink::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', ParentLinkStatus::Approved->value)
            ->pluck('parent_user_id');

        foreach (User::query()->active()->whereIn('id', $parentIds)->get() as $parent) {
            $recipients->put($parent->id, $parent);
        }

        if ($creator) {
            $recipients->forget($creator->id);
        }

        $this->sendMany($recipients->values(), 'New activity: '.$activity->name, $body, $url);
    }
}
