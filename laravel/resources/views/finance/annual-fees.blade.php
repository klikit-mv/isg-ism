<x-app-layout title="Annual fees">
    <x-page-header title="Annual fees" description="Yearly membership fees for scouts and leaders.">
        <x-slot:actions>
            @if ($canManageYears)
                <a href="{{ route('annual-fees.years') }}" class="btn-secondary">Fee years</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid grid-cols-3 gap-3">
        <x-stat label="Records" :value="$stats['records']"/>
        <x-stat label="Fully paid" :value="$stats['paid']" tone="gold"/>
        <x-stat label="Total billed" :value="scout_money($stats['billed'])"/>
    </div>

    <x-filters>
        <x-form.input name="q" label="Name or National ID" :value="request('q')"/>
        <x-form.select name="year" label="Year" :options="$years" :value="request('year')" placeholder="Any year"/>
        <x-form.select name="section" label="Section" :options="\App\Enums\ScoutSection::options()" :value="request('section')" placeholder="Any section"/>
        <x-form.select name="status" label="Status" :options="collect(\App\Enums\FeeStatus::options())->except('Void')->all()" :value="request('status')" placeholder="Any status"/>
    </x-filters>

    @if ($fees->isEmpty())
        <x-empty message="No annual fees match these filters."/>
    @else
        <x-table :headers="['Year', 'Person', 'Type', 'Section', 'Fee', 'Paid', 'Outstanding', 'Status', '']">
            @foreach ($fees as $fee)
                <tr>
                    <td data-label="Year">{{ $fee->feeYear?->year }}</td>
                    <td data-label="Person" class="font-medium">{{ $fee->personName() }}</td>
                    <td data-label="Type">{{ $fee->person_type->label() }}</td>
                    <td data-label="Section">{{ $fee->section?->value ?? $fee->student?->section?->value ?? '—' }}</td>
                    <td data-label="Fee">{{ scout_money($fee->amount) }}</td>
                    <td data-label="Paid">{{ scout_money($fee->paid_amount) }}</td>
                    <td data-label="Outstanding">{{ scout_money($fee->outstanding_amount) }}</td>
                    <td data-label="Status"><x-badge :value="$fee->status"/></td>
                    <td class="text-right"><x-pay-button :payable="$fee"/></td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $fees->links() }}</div>
    @endif

    <x-payment-modal/>
</x-app-layout>
