<x-app-layout title="My fees">
    <x-page-header title="My fees" description="Class fees and annual fees. Pay online with a proof of transfer."/>

    <h2 class="mb-2 font-semibold">Class fees</h2>
    @if ($classFees->isEmpty())
        <x-empty message="No class fees." class="mb-6"/>
    @else
        <div class="mb-6">
            <x-table :headers="['Activity', 'Fee', 'Paid', 'Outstanding', 'Status', '']">
                @foreach ($classFees as $fee)
                    <tr>
                        <td data-label="Activity">{{ $fee->activity?->name }} <span class="block text-xs text-gray-400">{{ scout_date($fee->activity?->date) }}</span></td>
                        <td data-label="Fee">{{ scout_money($fee->amount) }}</td>
                        <td data-label="Paid">{{ scout_money($fee->paid_amount) }}</td>
                        <td data-label="Outstanding">{{ scout_money($fee->outstanding_amount) }}</td>
                        <td data-label="Status"><x-badge :value="$fee->status"/></td>
                        <td class="text-right"><x-pay-button :payable="$fee"/></td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif

    <h2 class="mb-2 font-semibold">Annual fees</h2>
    @if ($annualFees->isEmpty())
        <x-empty message="No annual fees."/>
    @else
        <x-table :headers="['Year', 'Fee', 'Paid', 'Outstanding', 'Status', '']">
            @foreach ($annualFees as $fee)
                <tr>
                    <td data-label="Year">{{ $fee->feeYear?->year }}</td>
                    <td data-label="Fee">{{ scout_money($fee->amount) }}</td>
                    <td data-label="Paid">{{ scout_money($fee->paid_amount) }}</td>
                    <td data-label="Outstanding">{{ scout_money($fee->outstanding_amount) }}</td>
                    <td data-label="Status"><x-badge :value="$fee->status"/></td>
                    <td class="text-right"><x-pay-button :payable="$fee"/></td>
                </tr>
            @endforeach
        </x-table>
    @endif

    <x-payment-modal/>
</x-app-layout>
