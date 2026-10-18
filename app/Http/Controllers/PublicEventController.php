<?php

namespace App\Http\Controllers;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Upcoming events anyone can see without signing in. Registering needs an account.
 */
class PublicEventController extends Controller
{
    public function home(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        $events = Event::query()
            ->publiclyVisible()
            ->withCount(['registrations as registered_count' => fn ($q) => $q->where('status', EventRegistrationStatus::Registered->value)])
            ->orderBy('starts_at')
            ->limit(24)
            ->get();

        return view('public.home', ['events' => $events]);
    }

    public function show(Event $event): View
    {
        abort_unless(Event::query()->publiclyVisible()->whereKey($event->id)->exists(), 404);

        $event->load(['items' => fn ($q) => $q->where('active', true)]);

        return view('public.event', ['event' => $event, 'registeredCount' => $event->registeredCount()]);
    }
}
