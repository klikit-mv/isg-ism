@php
    $activeItems = $event->items->where('active', true)->values();
    $isFull = $event->capacity !== null && $registeredCount >= $event->capacity;
@endphp
<x-app-layout :title="$event->name">
    <x-page-header :title="$event->name" :description="scout_datetime($event->starts_at).($event->location ? ' · '.$event->location : '')">
        <x-slot:actions>
            <x-badge :value="$event->status"/>
            @if (in_array($event->status, [\App\Enums\EventStatus::Open, \App\Enums\EventStatus::Closed], true))
                <a href="{{ route('public.events.show', $event) }}" class="btn-secondary" target="_blank" rel="noopener" title="Anyone can open this page without signing in">Public page</a>
            @endif
            @if ($manage)
                <a href="{{ route('events.edit', $event) }}" class="btn-secondary">Edit event</a>
                @foreach (\App\Enums\EventStatus::cases() as $status)
                    @continue($status === $event->status)
                    <form method="POST" action="{{ route('events.status', $event) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $status->value }}">
                        <button class="{{ $status === \App\Enums\EventStatus::Open ? 'btn-primary' : 'btn-secondary' }}">
                            {{ match ($status) {
                                \App\Enums\EventStatus::Draft => 'Back to draft',
                                \App\Enums\EventStatus::Open => 'Open registration',
                                \App\Enums\EventStatus::Closed => 'Close registration',
                                \App\Enums\EventStatus::Cancelled => 'Cancel event',
                            } }}
                        </button>
                    </form>
                @endforeach
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Details --}}
            <section class="card">
                <h2 class="mb-3 text-lg font-semibold">Details</h2>
                @if ($event->description)
                    <p class="mb-4 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $event->description }}</p>
                @endif
                <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500 dark:text-gray-400">Starts</dt><dd>{{ scout_datetime($event->starts_at) }}</dd></div>
                    @if ($event->ends_at)
                        <div><dt class="text-gray-500 dark:text-gray-400">Ends</dt><dd>{{ scout_datetime($event->ends_at) }}</dd></div>
                    @endif
                    <div><dt class="text-gray-500 dark:text-gray-400">Registration fee</dt><dd>{{ \App\Support\Money::isPositive($event->fee) ? scout_money($event->fee) : 'Free' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Open to</dt><dd>{{ $event->audienceLabel() }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Registered</dt><dd>{{ $registeredCount }}{{ $event->capacity ? ' of '.$event->capacity.' places' : '' }}</dd></div>
                    @if ($event->registration_closes_at)
                        <div><dt class="text-gray-500 dark:text-gray-400">Registration closes</dt><dd>{{ scout_datetime($event->registration_closes_at) }}</dd></div>
                    @endif
                </dl>
            </section>

            {{-- Pre-order items --}}
            <section class="card" data-testid="event-items">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">Pre-order items</h2>
                    @if ($manage)
                        <button type="button" class="btn-primary btn-sm" x-data x-on:click="$dispatch('open-modal', 'add-event-item')">Add item</button>
                    @endif
                </div>
                @php $shownItems = $manage ? $event->items : $activeItems; @endphp
                @if ($shownItems->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $manage ? 'Add items scouts can order with their registration, such as a T-shirt or badge.' : 'No items to pre-order for this event.' }}</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($shownItems as $item)
                            <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="font-medium">{{ $item->name }} <span class="text-navy-700 dark:text-navy-300">{{ scout_money($item->price) }}</span>
                                        @unless ($item->active)<x-badge value="Hidden" tone="gray"/>@endunless
                                    </div>
                                    @if ($item->description)<p class="text-sm text-gray-500 dark:text-gray-400">{{ $item->description }}</p>@endif
                                    <p class="text-xs text-gray-500">
                                        @if ($item->sizeList() && ! $item->measurements())Sizes: {{ implode(', ', $item->sizeList()) }} · @endif
                                        Up to {{ $item->max_per_registration }} each
                                        @if ($item->stock !== null) · {{ $item->remaining() }} left @endif
                                    </p>
                                    @include('events.partials.size-chart', ['item' => $item])
                                </div>
                                @if ($manage)
                                    <div class="flex gap-2">
                                        <button type="button" class="btn-secondary btn-sm" x-data x-on:click="$dispatch('open-modal', 'edit-item-{{ $item->uuid }}')">Edit</button>
                                        <x-confirm :action="route('events.items.destroy', [$event, $item])" method="DELETE" label="Remove" message="Remove {{ $item->name }}? If it has already been ordered it is hidden instead, and existing orders stay." confirm="Remove"/>
                                    </div>
                                    <x-modal :name="'edit-item-'.$item->uuid" :title="'Edit '.$item->name" maxWidth="lg">
                                        <form method="POST" action="{{ route('events.items.update', [$event, $item]) }}" class="space-y-4">
                                            @csrf
                                            @method('PUT')
                                            @include('events.partials.item-fields', ['item' => $item, 'prefix' => 'item-'.$item->uuid])
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'edit-item-{{ $item->uuid }}')">Cancel</button>
                                                <button class="btn-primary">Save item</button>
                                            </div>
                                        </form>
                                    </x-modal>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Everyone registered (managers) --}}
            @if ($manage)
                <section data-testid="event-registrations">
                    <h2 class="mb-3 text-lg font-semibold">Registrations</h2>
                    @if ($registrations->isEmpty())
                        <x-empty message="Nobody has registered yet."/>
                    @else
                        <x-table :headers="['Scout', 'Items', 'Total', 'Paid', 'Payment', 'Status', '']">
                            @foreach ($registrations as $registration)
                                <tr>
                                    <td data-label="Scout">
                                        <div class="font-medium">{{ $registration->participantName() }}</div>
                                        <div class="text-xs text-gray-500">{{ $registration->participantRole() }} · by {{ $registration->registrar?->name }}</div>
                                    </td>
                                    <td data-label="Items">
                                        @forelse ($registration->items as $line)
                                            <div>{{ $line->quantity }} × {{ $line->item_name }}{{ $line->size ? ' ('.$line->size.')' : '' }}</div>
                                        @empty
                                            <span class="text-gray-400">—</span>
                                        @endforelse
                                        @if ($registration->notes)<div class="text-xs text-gray-500">“{{ $registration->notes }}”</div>@endif
                                    </td>
                                    <td data-label="Total">{{ scout_money($registration->total_amount) }}</td>
                                    <td data-label="Paid">{{ scout_money($registration->paid_amount) }}</td>
                                    <td data-label="Payment"><x-badge :value="$registration->payment_status"/><div class="mt-1 text-xs text-gray-500">{{ $registration->payment_option?->label() }}</div></td>
                                    <td data-label="Status"><x-badge :value="$registration->status"/></td>
                                    <td class="whitespace-nowrap text-right">
                                        <div class="flex flex-wrap justify-end gap-1">
                                            <x-pay-button :payable="$registration"/>
                                            @if ($registration->isActive() && ! \App\Support\Money::isPositive($registration->paid_amount))
                                                <x-confirm :action="route('event-registrations.cancel', $registration)" label="Cancel" variant="secondary" message="Cancel {{ $registration->participantName() }}'s registration?" confirm="Cancel registration"/>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </x-table>
                    @endif
                </section>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Register --}}
            <section class="card" data-testid="event-register">
                <h2 class="mb-3 text-lg font-semibold">Register</h2>
                @if (! $event->acceptsRegistrations())
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $event->status === \App\Enums\EventStatus::Open ? 'Registration has closed.' : 'Registration is not open. ('.$event->status->label().')' }}
                    </p>
                @elseif ($isFull)
                    <p class="text-sm text-amber-700 dark:text-amber-300">This event is full.</p>
                @elseif ($eligible->isEmpty() && ! $canRegisterSelf)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $myRegistrations->where('status', \App\Enums\EventRegistrationStatus::Registered)->isNotEmpty() ? 'Everyone you can register is already registered.' : 'You have no scouts who can join this event ('.$event->audienceLabel().').' }}
                    </p>
                @else
                    <form method="POST" action="{{ route('events.register', $event) }}" class="space-y-4"
                        x-data="{
                            fee: {{ (float) $event->fee }},
                            prices: @js($activeItems->mapWithKeys(fn ($i) => [$i->uuid => (float) $i->price])),
                            qty: @js($activeItems->mapWithKeys(fn ($i, $index) => [$i->uuid => (int) old('items.'.$index.'.quantity', 0)])),
                            get total() { return this.fee + Object.keys(this.prices).reduce((sum, key) => sum + this.prices[key] * (parseInt(this.qty[key]) || 0), 0); },
                        }">
                        @csrf
                        @php
                            $participants = ($canRegisterSelf ? [\App\Http\Controllers\EventRegistrationController::MYSELF => 'Myself ('.auth()->user()->name.', leader)'] : [])
                                + $eligible->mapWithKeys(fn ($s) => [$s->uuid => $s->name.' ('.$s->section?->value.')'])->all();
                        @endphp
                        <x-form.select name="student" label="Who is taking part?" :options="$participants" :value="count($participants) === 1 ? array_key_first($participants) : null" placeholder="Choose" required/>

                        @foreach ($activeItems as $index => $item)
                            @php $left = $item->remaining(); @endphp
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                <input type="hidden" name="items[{{ $index }}][item]" value="{{ $item->uuid }}">
                                <div class="mb-2 flex items-center justify-between text-sm">
                                    <span class="font-medium">{{ $item->name }}</span>
                                    <span>{{ scout_money($item->price) }}</span>
                                </div>
                                @if ($left === 0)
                                    <p class="text-xs text-rose-600">Sold out</p>
                                    <input type="hidden" name="items[{{ $index }}][quantity]" value="0">
                                @else
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="label" for="qty-{{ $item->uuid }}">Quantity</label>
                                            <input id="qty-{{ $item->uuid }}" name="items[{{ $index }}][quantity]" type="number" min="0" max="{{ min($item->max_per_registration, $left ?? PHP_INT_MAX) }}" class="input" x-model="qty['{{ $item->uuid }}']">
                                        </div>
                                        @if ($item->sizeList())
                                            <div>
                                                <label class="label" for="size-{{ $item->uuid }}">Size</label>
                                                <select id="size-{{ $item->uuid }}" name="items[{{ $index }}][size]" class="input">
                                                    <option value="">—</option>
                                                    @foreach ($item->sizeList() as $size)
                                                        <option value="{{ $size }}" @selected(old('items.'.$index.'.size') === $size)>{{ $item->sizeLabel($size) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <fieldset>
                            <legend class="label">How will you pay?</legend>
                            <div class="space-y-1">
                                @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="radio" name="payment_option" value="{{ $method->value }}" @checked(old('payment_option', 'online') === $method->value) class="text-navy-600 focus:ring-navy-500">
                                        <span>{{ $method === \App\Enums\PaymentMethod::Online ? 'Online transfer (upload proof now)' : 'Cash to a leader' }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('payment_option')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </fieldset>

                        <x-form.input name="notes" label="Notes (optional)" maxlength="500" placeholder="Allergies, pick-up, anything the leaders should know"/>

                        <div class="flex items-center justify-between border-t border-gray-200 pt-3 dark:border-gray-700">
                            <span class="text-sm text-gray-500">Total</span>
                            <span class="text-lg font-bold" x-text="'{{ config('scout.currency') }} ' + total.toFixed(2)">{{ scout_money($event->fee) }}</span>
                        </div>
                        <button type="submit" class="btn-primary w-full">Register</button>
                    </form>
                @endif
            </section>

            {{-- The user's own registrations --}}
            @if ($myRegistrations->isNotEmpty())
                <section class="card" data-testid="my-registrations">
                    <h2 class="mb-3 text-lg font-semibold">Your registrations</h2>
                    <ul class="space-y-3">
                        @foreach ($myRegistrations as $registration)
                            <li class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium">{{ $registration->isLeaderRegistration() ? 'You (leader)' : $registration->participantName() }}</span>
                                    <x-badge :value="$registration->isActive() ? $registration->payment_status : $registration->status"/>
                                </div>
                                @foreach ($registration->items as $line)
                                    <div class="text-gray-600 dark:text-gray-300">{{ $line->quantity }} × {{ $line->item_name }}{{ $line->size ? ' ('.$line->size.')' : '' }}</div>
                                @endforeach
                                <div class="mt-1 text-gray-500">Total {{ scout_money($registration->total_amount) }} · Paid {{ scout_money($registration->paid_amount) }}</div>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <x-pay-button :payable="$registration"/>
                                    @if ($registration->isActive() && ! \App\Support\Money::isPositive($registration->paid_amount))
                                        <x-confirm :action="route('event-registrations.cancel', $registration)" label="Cancel" variant="secondary" message="Cancel this registration?" confirm="Cancel registration"/>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- What to order (managers) --}}
            @if ($manage)
                <section class="card" data-testid="order-summary">
                    <h2 class="mb-3 text-lg font-semibold">Order summary</h2>
                    @if ($summary->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No items ordered yet.</p>
                    @else
                        <table class="w-full text-sm">
                            <thead><tr class="text-left text-gray-500"><th class="py-1">Item</th><th>Size</th><th class="text-right">Qty</th><th class="text-right">Amount</th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($summary as $row)
                                    <tr><td class="py-1">{{ $row['item'] }}</td><td>{{ $row['size'] ?? '—' }}</td><td class="text-right">{{ $row['quantity'] }}</td><td class="text-right">{{ scout_money($row['amount']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            @endif
        </div>
    </div>

    @if ($manage)
        <x-modal name="add-event-item" title="Add pre-order item" maxWidth="lg">
            <form method="POST" action="{{ route('events.items.store', $event) }}" class="space-y-4">
                @csrf
                @include('events.partials.item-fields', ['item' => new \App\Models\EventItem(['max_per_registration' => 1, 'active' => true]), 'prefix' => 'new-item'])
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'add-event-item')">Cancel</button>
                    <button class="btn-primary">Add item</button>
                </div>
            </form>
        </x-modal>
    @endif

    <x-payment-modal/>

    @if ($openPayment = session('open_payment'))
        @php $toPay = $myRegistrations->firstWhere('uuid', $openPayment) ?? $registrations->firstWhere('uuid', $openPayment); @endphp
        @if ($toPay && $toPay->isActive() && \App\Support\Money::isPositive($toPay->outstanding_amount))
            <div x-data x-init="$nextTick(() => $dispatch('pay', { type: @js($toPay->payableTypeKey()), id: @js($toPay->uuid), amount: @js((string) $toPay->outstanding_amount), description: @js($toPay->payableDescription().' — outstanding '.scout_money($toPay->outstanding_amount)) }))"></div>
        @endif
    @endif
</x-app-layout>
