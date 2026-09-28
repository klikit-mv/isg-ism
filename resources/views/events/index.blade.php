<x-app-layout title="Events">
    <x-page-header title="Events" description="Camps, hikes and gatherings. Register a scout, pre-order items and pay in one place.">
        <x-slot:actions>
            <a href="{{ route('events.index', $past ? [] : ['show' => 'past']) }}" class="btn-secondary">{{ $past ? 'Upcoming events' : 'Past events' }}</a>
            <a href="{{ route('event-registrations.index') }}" class="btn-secondary">Registrations</a>
            @if ($canCreate)
                <a href="{{ route('events.create') }}" class="btn-primary">New event</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($events->isEmpty())
        <x-empty :message="$past ? 'No past events.' : 'No upcoming events yet.'"/>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($events as $event)
                <a href="{{ route('events.show', $event) }}" class="card flex flex-col gap-3 transition hover:shadow-md" data-event="{{ $event->uuid }}">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $event->name }}</h2>
                        <x-badge :value="$event->status"/>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">When</dt>
                        <dd>{{ scout_datetime($event->starts_at) }}</dd>
                        @if ($event->location)
                            <dt class="text-gray-500 dark:text-gray-400">Where</dt>
                            <dd>{{ $event->location }}</dd>
                        @endif
                        <dt class="text-gray-500 dark:text-gray-400">Fee</dt>
                        <dd>{{ \App\Support\Money::isPositive($event->fee) ? scout_money($event->fee) : 'Free' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">For</dt>
                        <dd>{{ $event->sectionsLabel() }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Registered</dt>
                        <dd>{{ $event->registered_count }}{{ $event->capacity ? ' / '.$event->capacity : '' }}</dd>
                    </dl>
                    @if ($event->registration_closes_at && $event->status === \App\Enums\EventStatus::Open)
                        <p class="text-xs text-amber-700 dark:text-amber-300">Registration closes {{ scout_datetime($event->registration_closes_at) }}</p>
                    @endif
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $events->links() }}</div>
    @endif
</x-app-layout>
