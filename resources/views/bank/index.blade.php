<x-app-layout title="Bank">
    <x-page-header title="Bank accounts" description="How much money the group holds, with deposit slips and spending.">
        <x-slot:actions>
            <button type="button" class="btn-primary" x-data x-on:click="$dispatch('open-modal', 'create-account')">Add account</button>
        </x-slot:actions>
    </x-page-header>

    @if ($accounts->isEmpty())
        <x-empty message="No bank account yet. Add one to start recording deposits and spending."/>
    @else
        <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-stat label="Total in the bank" :value="scout_money($accounts->where('status', 'Active')->reduce(fn ($sum, $a) => \App\Support\Money::add($sum, $a->balance()), '0'))" tone="gold"/>
        </div>
        <x-table :headers="['Account', 'Bank', 'Number', 'Status', 'Online payments', 'Balance', '']">
            @foreach ($accounts as $account)
                <tr>
                    <td data-label="Account" class="font-medium"><a href="{{ route('bank.show', $account) }}" class="hover:underline">{{ $account->name }}</a></td>
                    <td data-label="Bank">{{ $account->bank_name }}</td>
                    <td data-label="Number">{{ $account->account_number }}</td>
                    <td data-label="Status"><x-badge :value="$account->status" :tone="$account->status === 'Active' ? 'green' : null"/></td>
                    <td data-label="Online payments">{{ $account->receives_online ? scout_money($account->totalOnline()) : '—' }}</td>
                    <td data-label="Balance" class="font-semibold">{{ scout_money($account->balance()) }}</td>
                    <td class="text-right"><a href="{{ route('bank.show', $account) }}" class="link">Open</a></td>
                </tr>
            @endforeach
        </x-table>
    @endif

    <x-modal name="create-account" title="Add bank account" maxWidth="md">
        <form method="POST" action="{{ route('bank.store') }}" class="space-y-4">
            @csrf
            <x-form.input name="name" label="Name" placeholder="General fund" required/>
            <x-form.input name="bank_name" label="Bank" required/>
            <x-form.input name="account_name" label="Account holder"/>
            <x-form.input name="account_number" label="Account number" required/>
            <x-form.input name="opening_balance" label="Money already in the account" type="number" step="0.01" min="0" value="0"/>
            <x-form.checkbox name="receives_online" label="Receives online payments (verified ones are added to the balance)"/>
            <x-form.textarea name="notes" label="Notes"/>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'create-account')">Cancel</button>
                <button type="submit" class="btn-primary">Add</button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
