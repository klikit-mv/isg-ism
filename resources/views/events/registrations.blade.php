<x-app-layout title="Event registrations">
    <x-page-header title="Event registrations" description="Registrations for your scouts. Pay any outstanding amount here.">
        <x-slot:actions>
            <a href="{{ route('events.index') }}" class="btn-secondary">All events</a>
        </x-slot:actions>
    </x-page-header>

    @if ($registrations->isEmpty())
        <x-empty message="No event registrations yet."/>
    @else
        <x-table :headers="['Event', 'Participant', 'Items', 'Total', 'Paid', 'Payment', 'Status', '']">
            @foreach ($registrations as $registration)
                <tr>
                    <td data-label="Event">
                        <a href="{{ route('events.show', $registration->event) }}" class="font-medium text-navy-700 hover:underline dark:text-navy-300">{{ $registration->event?->name }}</a>
                        <div class="text-xs text-gray-500">{{ scout_date($registration->event?->starts_at) }}</div>
                    </td>
                    <td data-label="Participant">{{ $registration->participantName() }}<div class="text-xs text-gray-500">{{ $registration->participantRole() }}</div></td>
                    <td data-label="Items">
                        @forelse ($registration->items as $line)
                            <div>{{ $line->quantity }} × {{ $line->item_name }}{{ $line->size ? ' ('.$line->size.')' : '' }}</div>
                        @empty
                            <span class="text-gray-400">—</span>
                        @endforelse
                    </td>
                    <td data-label="Total">{{ scout_money($registration->total_amount) }}</td>
                    <td data-label="Paid">{{ scout_money($registration->paid_amount) }}</td>
                    <td data-label="Payment"><x-badge :value="$registration->payment_status"/><div class="mt-1 text-xs text-gray-500">{{ $registration->payment_option?->label() }}</div></td>
                    <td data-label="Status"><x-badge :value="$registration->status"/></td>
                    <td class="whitespace-nowrap text-right">
                        <div class="flex flex-wrap justify-end gap-1">
                            <x-pay-button :payable="$registration"/>
                            @if ($registration->isActive() && ! \App\Support\Money::isPositive($registration->paid_amount))
                                <x-confirm :action="route('event-registrations.cancel', $registration)" label="Cancel" variant="secondary" message="Cancel this registration?" confirm="Cancel registration"/>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $registrations->links() }}</div>
    @endif

    <x-payment-modal/>
</x-app-layout>
