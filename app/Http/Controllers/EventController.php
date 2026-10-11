<?php

namespace App\Http\Controllers;

use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Enums\ScoutSection;
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

class EventController extends Controller
{
    public function __construct(private EventService $events, private LeaderScopeService $scope) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $past = $request->query('show') === 'past';

        $events = Event::query()
            ->withCount(['registrations as registered_count' => fn ($q) => $q->where('status', EventRegistrationStatus::Registered->value)])
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($w) => $w
                ->where('status', '!=', EventStatus::Draft->value)
                ->when($user->isLeader(), fn ($x) => $x->orWhere('created_by', $user->id))))
            ->when($past, fn ($q) => $q->where('starts_at', '<', scout_now()->startOfDay()->utc()), fn ($q) => $q->where('starts_at', '>=', scout_now()->startOfDay()->utc()))
            ->orderBy('starts_at', $past ? 'desc' : 'asc')
            ->paginate(Pagination::MAX)
            ->withQueryString();

        return view('events.index', ['events' => $events, 'past' => $past, 'canCreate' => $user->isStaff()]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->isStaff(), 403);

        return view('events.form', ['event' => new Event(['starts_at' => scout_now()->addWeek()->setTime(9, 0), 'fee' => '0.00'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isStaff(), 403);
        $event = $this->events->create($this->validated($request), $request->user());

        return redirect()->route('events.show', $event)->with('success', 'The event was created as a draft. Add any pre-order items, then open registration.');
    }

    public function show(Request $request, Event $event): View
    {
        $user = $request->user();
        $manage = $this->events->canManage($user, $event);
        abort_if($event->status === EventStatus::Draft && ! $manage, 404);

        $event->load('items', 'creator');
        $accessible = $this->scope->constrainStudents(Student::query()->active(), $user)->orderBy('name')->get()
            ->filter(fn (Student $s) => $this->scope->canAccessStudent($user, $s));
        $registeredIds = $event->registrations()->where('status', EventRegistrationStatus::Registered->value)->whereNotNull('student_id')->pluck('student_id')->all();

        $myRegistrations = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where(fn ($q) => $q->whereIn('student_id', $accessible->pluck('id')->all() ?: [0])->orWhere('user_id', $user->id))
            ->with('student', 'user', 'items')
            ->latest()
            ->get();

        return view('events.show', [
            'event' => $event,
            'manage' => $manage,
            'eligible' => $accessible->filter(fn (Student $s) => $s->section && $event->isOpenForSection($s->section) && ! in_array($s->id, $registeredIds, true))->values(),
            'myRegistrations' => $myRegistrations,
            'canRegisterSelf' => $user->isLeader() && ! $event->registrations()->where('user_id', $user->id)->where('status', EventRegistrationStatus::Registered->value)->exists(),
            'registrations' => $manage ? $event->registrations()->with('student', 'user', 'items', 'registrar')->orderByDesc('status')->latest()->get() : collect(),
            'summary' => $manage ? $this->events->orderSummary($event) : collect(),
            'registeredCount' => $event->registeredCount(),
        ]);
    }

    public function edit(Request $request, Event $event): View
    {
        abort_unless($this->events->canManage($request->user(), $event), 403);

        return view('events.form', ['event' => $event]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->events->canManage($request->user(), $event), 403);
        $this->events->update($event, $this->validated($request), $request->user());

        return redirect()->route('events.show', $event)->with('success', 'The event was saved.');
    }

    public function status(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->events->canManage($request->user(), $event), 403);
        $data = $request->validate(['status' => ['required', Rule::enum(EventStatus::class)]]);
        $status = EventStatus::from($data['status']);
        $this->events->setStatus($event, $status, $request->user());

        return back()->with('success', "The event is now: {$status->label()}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registration_closes_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'fee' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'sections' => ['array'],
            'sections.*' => [Rule::enum(ScoutSection::class)],
        ]);
    }
}
