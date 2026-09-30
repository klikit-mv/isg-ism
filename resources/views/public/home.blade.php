<x-public-layout>
    <section class="mb-8 rounded-2xl bg-gradient-to-br from-navy-800 via-navy-700 to-navy-900 px-6 py-10 text-white shadow-lg sm:px-10">
        <h1 class="text-3xl font-bold">{{ config('scout.name') }}</h1>
        <p class="mt-2 max-w-2xl text-navy-100">Upcoming camps, hikes and gatherings. Browse the details here, then sign in to register and pay.</p>
        <div class="mt-5 flex flex-wrap gap-2">
            <a href="{{ route('login') }}" class="btn-accent">Sign in</a>
            <a href="{{ route('register') }}" class="rounded-lg border border-white/40 px-4 py-2 text-sm font-medium hover:bg-white/10">Create an account</a>
        </div>
    </section>

    <h2 class="mb-4 text-xl font-semibold">Upcoming events</h2>

    @if ($events->isEmpty())
        <x-empty message="No upcoming events right now. Check back soon."/>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($events as $event)
                <a href="{{ route('public.events.show', $event) }}" class="card flex flex-col gap-3 transition hover:shadow-md" data-event="{{ $event->uuid }}">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $event->name }}</h3>
                        <x-badge :value="$event->acceptsRegistrations() ? 'Registration open' : 'Registration closed'" :tone="$event->acceptsRegistrations() ? 'green' : 'amber'"/>
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
                        <dd>{{ $event->audienceLabel() }}</dd>
                    </dl>
                    <span class="mt-auto text-sm font-medium text-navy-700 dark:text-navy-300">View details →</span>
                </a>
            @endforeach
        </div>
    @endif
</x-public-layout>
