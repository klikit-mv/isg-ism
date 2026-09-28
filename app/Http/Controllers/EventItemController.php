<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventItem;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pre-order items (T-shirts, badges…) for an event.
 */
class EventItemController extends Controller
{
    public function __construct(private EventService $events) {}

    public function store(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->events->canManage($request->user(), $event), 403);
        $item = $this->events->saveItem($event, $this->validated($request), $request->user());

        return back()->with('success', "{$item->name} was added.");
    }

    public function update(Request $request, Event $event, EventItem $item): RedirectResponse
    {
        abort_unless($this->events->canManage($request->user(), $event) && $item->event_id === $event->id, 403);
        $this->events->saveItem($event, $this->validated($request), $request->user(), $item);

        return back()->with('success', "{$item->name} was saved.");
    }

    public function destroy(Request $request, Event $event, EventItem $item): RedirectResponse
    {
        abort_unless($this->events->canManage($request->user(), $event) && $item->event_id === $event->id, 403);
        $this->events->deleteItem($item, $request->user());

        return back()->with('success', 'The item was removed. Items already ordered are kept on those registrations.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'sizes' => ['nullable', 'string', 'max:2000'],
            'size_guide' => ['nullable', 'string', 'max:1000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_per_registration' => ['required', 'integer', 'min:1', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active', true);

        return $data;
    }
}
