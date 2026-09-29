<?php

namespace App\Http\Controllers;

use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Student;
use App\Services\EventService;
use App\Services\LeaderScopeService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventRegistrationController extends Controller
{
    /**
     * The "student" value a leader sends to register themselves.
     */
    public const MYSELF = 'me';

    public function __construct(private EventService $events, private LeaderScopeService $scope) {}

    /**
     * Registrations for the scouts this user can act for.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $ids = $this->scope->getLeaderStudentIds($user);

        $registrations = EventRegistration::query()
            ->with('event', 'student', 'items')
            ->with('user')
            ->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('student_id', $ids === [] ? [0] : $ids)->orWhere('user_id', $user->id)->orWhere('registered_by', $user->id)))
            ->latest()
            ->paginate(Pagination::MAX);

        return view('events.registrations', ['registrations' => $registrations]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'student' => ['required', 'string', 'max:64'],
            'payment_option' => ['required', Rule::enum(PaymentMethod::class)],
            'items' => ['array'],
            'items.*.item' => ['required', 'uuid'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'items.*.size' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $participant = $data['student'] === self::MYSELF
            ? $request->user()
            : Student::query()->where('uuid', $data['student'])->firstOrFail();
        $registration = $this->events->register($event, $participant, array_values($data['items'] ?? []), PaymentMethod::from($data['payment_option']), $request->user(), $data['notes'] ?? null);

        $who = $participant instanceof Student ? "{$participant->name} is" : 'You are';
        $message = "{$who} registered for {$event->name}. Total: ".scout_money($registration->total_amount).'.';
        $redirect = redirect()->route('events.show', $event);

        if ($registration->payment_status === FeeStatus::Paid) {
            return $redirect->with('success', $message.' Nothing to pay.');
        }

        if ($registration->payment_option === PaymentMethod::Online) {
            return $redirect->with('success', $message.' Upload your payment proof now.')->with('open_payment', $registration->uuid);
        }

        return $redirect->with('success', $message.' Please pay in cash to a leader; they will record it.');
    }

    public function cancel(Request $request, EventRegistration $registration): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $this->events->canManage($user, $registration->event)
            || ($registration->user_id !== null && $registration->user_id === $user->id)
            || ($registration->student && $this->scope->canAccessStudent($user, $registration->student)),
            403,
        );

        $this->events->cancel($registration, $user);

        return back()->with('success', 'The registration was cancelled.');
    }
}
