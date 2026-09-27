<x-app-layout title="Annual fee years">
    <x-page-header title="Annual fee years" description="Open a year, then generate invoices for scouts and leaders.">
        <x-slot:actions>
            <a href="{{ route('annual-fees.index') }}" class="btn-secondary">Annual fees</a>
            @if (auth()->user()->isAdmin())
                <button type="button" class="btn-primary" x-data x-on:click="$dispatch('open-modal', 'create-year')">Create year</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($years->isEmpty())
        <x-empty message="No fee years yet."/>
    @else
        <x-table :headers="['Year', 'Amount', 'Invoices', 'Status', '']">
            @foreach ($years as $year)
                <tr>
                    <td data-label="Year" class="font-medium">{{ $year->year }}</td>
                    <td data-label="Amount">{{ scout_money($year->amount) }}</td>
                    <td data-label="Invoices">{{ $year->fees_count }}</td>
                    <td data-label="Status"><x-badge :value="$year->status"/></td>
                    <td class="whitespace-nowrap text-right">
                        @if ($year->status === \App\Enums\RecordStatus::Active)
                            <a href="{{ route('annual-fees.generate', $year) }}" class="btn-primary btn-sm">Generate invoices</a>
                        @endif
                        @if (auth()->user()->isAdmin())
                            <form method="POST" action="{{ route('annual-fees.years.status', $year) }}" class="inline">
                                @csrf
                                <input type="hidden" name="status" value="{{ $year->status === \App\Enums\RecordStatus::Active ? 'Inactive' : 'Active' }}">
                                <button class="btn-secondary btn-sm">{{ $year->status === \App\Enums\RecordStatus::Active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif

    @if (auth()->user()->isAdmin())
        <x-modal name="create-year" title="Create fee year" maxWidth="md">
            <form method="POST" action="{{ route('annual-fees.years.store') }}" class="space-y-4">
                @csrf
                <x-form.input name="year" label="Year" type="number" min="2000" :value="$suggestedYear" required/>
                <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0" required/>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'create-year')">Cancel</button>
                    <button class="btn-primary">Create</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
