<x-app-layout title="Class fees">
    <x-page-header title="Class fees" description="Fees created from attendance on charged activities."/>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Records" :value="$stats['records']"/>
        <x-stat label="Fully paid" :value="$stats['paid']" tone="gold"/>
        <x-stat label="Total billed" :value="scout_money($stats['billed'])"/>
        <x-stat label="Outstanding" :value="scout_money($stats['outstanding'])"/>
    </div>

    <x-filters>
        <x-form.input name="q" label="Scout" :value="request('q')" placeholder="Name, National ID or index"/>
        <x-form.select name="activity" label="Activity" :options="$activities" :value="request('activity')" placeholder="Any activity"/>
        <x-form.select name="section" label="Section" :options="\App\Enums\ScoutSection::options()" :value="request('section')" placeholder="Any section"/>
        <x-form.select name="status" label="Status" :options="collect(\App\Enums\FeeStatus::options())->except('Void')->all()" :value="request('status')" placeholder="Any status"/>
    </x-filters>

    @if ($fees->isEmpty())
        <x-empty message="No class fees match these filters."/>
    @else
        <x-table :headers="['Scout', 'Activity', 'Fee', 'Paid', 'Outstanding', 'Due', 'Status', '']">
            @foreach ($fees as $fee)
                <tr>
                    <td data-label="Scout" class="font-medium">{{ $fee->student?->name }}</td>
                    <td data-label="Activity">{{ $fee->activity?->name }} <span class="block text-xs text-gray-400">{{ scout_date($fee->activity?->date) }}</span></td>
                    <td data-label="Fee">{{ scout_money($fee->amount) }}</td>
                    <td data-label="Paid">{{ scout_money($fee->paid_amount) }}</td>
                    <td data-label="Outstanding">{{ scout_money($fee->outstanding_amount) }}</td>
                    <td data-label="Due">{{ scout_date($fee->due_date) }}</td>
                    <td data-label="Status"><x-badge :value="$fee->status"/></td>
                    <td class="text-right"><x-pay-button :payable="$fee"/></td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $fees->links() }}</div>
    @endif

    <x-payment-modal/>
</x-app-layout>
