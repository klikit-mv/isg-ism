<x-app-layout title="Annual fee years">
    <x-page-header title="Annual fee years" description="The annual fee is a yearly subscription: open each year and invoice every active scout again.">
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
        <x-table :headers="['Year', 'Amount', 'Invoices', 'Scouts not invoiced', 'Status', '']">
            @foreach ($years as $year)
                <tr>
                    <td data-label="Year" class="font-medium">{{ $year->year }}</td>
                    <td data-label="Amount">{{ scout_money($year->amount) }}</td>
                    <td data-label="Invoices">{{ $year->fees_count }}</td>
                    <td data-label="Scouts not invoiced">{{ $year->status === \App\Enums\RecordStatus::Active ? $year->scouts_without_invoice : '—' }}</td>
                    <td data-label="Status"><x-badge :value="$year->status"/></td>
                    <td class="whitespace-nowrap text-right">
                        @if ($year->status === \App\Enums\RecordStatus::Active)
                            <x-confirm :action="route('annual-fees.generate-all', $year)" label="Invoice all scouts" variant="accent" title="Invoice every active scout for {{ $year->year }}" :message="'Creates a '.scout_money($year->amount).' invoice for each of the '.$year->scouts_without_invoice.' active scouts who do not have one for '.$year->year.' yet. Scouts already invoiced are skipped.'" confirm="Create invoices">
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="include_leaders" value="1" class="rounded border-gray-300 text-navy-600"> Also invoice active leaders</label>
                            </x-confirm>
                            <a href="{{ route('annual-fees.generate', $year) }}" class="btn-primary btn-sm">Choose people</a>
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
                @if (auth()->user()->hasPermission(\App\Enums\Permission::ManageFees))
                    <x-form.checkbox name="invoice_everyone" label="Invoice every active scout now" :checked="true"/>
                @endif
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'create-year')">Cancel</button>
                    <button class="btn-primary">Create</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
