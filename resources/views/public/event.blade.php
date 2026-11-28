@php $isFull = $event->capacity !== null && $registeredCount >= $event->capacity; @endphp
<x-public-layout :title="$event->name">
    <a href="{{ route('home') }}" class="mb-4 inline-block text-sm text-navy-700 hover:underline dark:text-navy-300">← All events</a>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card">
                <h1 class="text-2xl font-bold">{{ $event->name }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ scout_datetime($event->starts_at) }}{{ $event->location ? ' · '.$event->location : '' }}</p>
                @if ($event->description)
                    <p class="mt-4 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $event->description }}</p>
                @endif
                <dl class="mt-4 grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500 dark:text-gray-400">Starts</dt><dd>{{ scout_datetime($event->starts_at) }}</dd></div>
                    @if ($event->ends_at)
                        <div><dt class="text-gray-500 dark:text-gray-400">Ends</dt><dd>{{ scout_datetime($event->ends_at) }}</dd></div>
                    @endif
                    <div><dt class="text-gray-500 dark:text-gray-400">Registration fee</dt><dd>{{ \App\Support\Money::isPositive($event->fee) ? scout_money($event->fee) : 'Free' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Open to</dt><dd>{{ $event->audienceLabel() }}</dd></div>
                    @if ($event->capacity)
                        <div><dt class="text-gray-500 dark:text-gray-400">Places</dt><dd>{{ max(0, $event->capacity - $registeredCount) }} of {{ $event->capacity }} left</dd></div>
                    @endif
                    @if ($event->registration_closes_at)
                        <div><dt class="text-gray-500 dark:text-gray-400">Registration closes</dt><dd>{{ scout_datetime($event->registration_closes_at) }}</dd></div>
                    @endif
                </dl>
            </section>

            @if ($event->items->isNotEmpty())
                <section class="card" data-testid="event-items">
                    <h2 class="mb-3 text-lg font-semibold">Pre-order items</h2>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($event->items as $item)
                            <li class="py-3">
                                <div class="font-medium">{{ $item->name }} <span class="text-navy-700 dark:text-navy-300">{{ scout_money($item->price) }}</span></div>
                                @if ($item->description)<p class="text-sm text-gray-500 dark:text-gray-400">{{ $item->description }}</p>@endif
                                @if ($item->sizeList() && ! $item->measurements())<p class="text-xs text-gray-500">Sizes: {{ implode(', ', $item->sizeList()) }}</p>@endif
                                @include('events.partials.size-chart', ['item' => $item])
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside>
            <section class="card" data-testid="public-register">
                <h2 class="mb-2 text-lg font-semibold">Register</h2>
                @if (! $event->acceptsRegistrations())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Registration is closed.</p>
                @elseif ($isFull)
                    <p class="text-sm text-amber-700 dark:text-amber-300">This event is full.</p>
                @else
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">Sign in to register, pre-order items and choose how to pay.</p>
                    <a href="{{ route('events.show', $event) }}" class="btn-primary w-full justify-center">{{ auth()->check() ? 'Register now' : 'Sign in to register' }}</a>
                    @guest
                        <p class="mt-3 text-center text-xs text-gray-500">No account yet? <a href="{{ route('register') }}" class="text-navy-700 hover:underline dark:text-navy-300">Create one</a>.</p>
                    @endguest
                @endif
            </section>
        </aside>
    </div>
</x-public-layout>
