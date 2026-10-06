<x-app-layout :title="$account->name">
    <x-page-header :title="$account->name" :description="$account->bank_name.' · '.$account->account_number">
        <x-slot:actions>
            <a href="{{ route('bank.index') }}" class="btn-secondary">All accounts</a>
            <button type="button" class="btn-secondary" x-data x-on:click="$dispatch('open-modal', 'edit-account')">Edit</button>
            @if ($account->status === 'Active')
                <button type="button" class="btn-primary" x-data x-on:click="$dispatch('open-modal', 'add-deposit')">Add deposit</button>
                <button type="button" class="btn-accent" x-data x-on:click="$dispatch('open-modal', 'add-expense')">Record spending</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($account->status !== 'Active')
        <p class="mb-4 text-sm text-amber-700 dark:text-amber-300">This account is inactive. Make it active to record entries.</p>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Opening balance" :value="scout_money($account->opening_balance)"/>
        <x-stat label="Deposits" :value="scout_money($account->totalDeposits())"/>
        <x-stat label="Spent" :value="scout_money($account->totalExpenses())"/>
        <x-stat label="Balance" :value="scout_money($account->balance())" tone="gold"/>
    </div>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Name, purpose or reference"/>
        <x-form.select name="type" label="Type" :options="['deposit' => 'Deposits', 'expense' => 'Spending']" :value="request('type')" placeholder="Everything"/>
        <x-form.input name="from" label="From" type="date" :value="request('from')"/>
        <x-form.input name="to" label="To" type="date" :value="request('to')"/>
    </x-filters>

    @if ($transactions->isEmpty())
        <x-empty message="No entries yet."/>
    @else
        <x-table :headers="['Date', 'Type', 'Collected from / requested by', 'Purpose and details', 'Amount', 'Recorded by', '']">
            @foreach ($transactions as $entry)
                <tr>
                    <td data-label="Date" class="whitespace-nowrap">{{ scout_date($entry->transaction_date) }}</td>
                    <td data-label="Type"><x-badge :value="$entry->isDeposit() ? 'Deposit' : 'Spending'" :tone="$entry->isDeposit() ? 'green' : 'red'"/></td>
                    <td data-label="Party">{{ $entry->party }}</td>
                    <td data-label="Purpose">
                        {{ $entry->purpose ?: '—' }}
                        @if ($entry->details)<div class="text-xs text-gray-500">{{ $entry->details }}</div>@endif
                        @if ($entry->reference)<div class="text-xs text-gray-400">Ref: {{ $entry->reference }}</div>@endif
                    </td>
                    <td data-label="Amount" class="whitespace-nowrap font-semibold {{ $entry->isDeposit() ? 'text-emerald-600' : 'text-rose-600' }}">{{ $entry->isDeposit() ? '+' : '−' }} {{ scout_money($entry->amount) }}</td>
                    <td data-label="Recorded by">{{ $entry->recorder?->name ?? '—' }}</td>
                    <td class="space-x-2 text-right">
                        @if ($entry->attachment_path)<a href="{{ route('bank.attachment', [$account, $entry]) }}" class="link" target="_blank" rel="noopener">{{ $entry->isDeposit() ? 'Slip' : 'Receipt' }}</a>@endif
                        @if (auth()->user()->isAdmin())
                            <x-confirm :action="route('bank.transactions.destroy', [$account, $entry])" method="DELETE" label="Delete" variant="secondary" message="Remove this entry? The balance is recalculated." confirm="Delete"/>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $transactions->links() }}</div>
    @endif

    <x-modal name="add-deposit" title="Add deposit" maxWidth="md">
        <form method="POST" action="{{ route('bank.record', [$account, 'deposit']) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0.01" required/>
            <x-form.input name="date" label="Deposit date" type="date" :value="now()->toDateString()" max="{{ now()->toDateString() }}" required/>
            <x-form.input name="party" label="Collected from" placeholder="Who or what the money was collected from" required/>
            <x-form.input name="purpose" label="Collected for" placeholder="Annual fees, camp, shop sales…"/>
            <x-form.textarea name="details" label="Details"/>
            <x-form.input name="reference" label="Slip number"/>
            <x-form.file-drop name="attachment" label="Deposit slip" accept=".pdf,.jpg,.jpeg,.png,.webp" help="PDF or image. Required." required/>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'add-deposit')">Cancel</button>
                <button type="submit" class="btn-primary">Save deposit</button>
            </div>
        </form>
    </x-modal>

    <x-modal name="add-expense" title="Record spending" maxWidth="md">
        <form method="POST" action="{{ route('bank.record', [$account, 'expense']) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <p class="text-sm text-gray-500">Available: <strong>{{ scout_money($account->balance()) }}</strong>. The amount is deducted from the balance.</p>
            <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0.01" required/>
            <x-form.input name="date" label="Date" type="date" :value="now()->toDateString()" max="{{ now()->toDateString() }}" required/>
            <x-form.input name="party" label="Requested by" required/>
            <x-form.input name="purpose" label="Purpose" required/>
            <x-form.textarea name="details" label="Details"/>
            <x-form.input name="reference" label="Reference"/>
            <x-form.file-drop name="attachment" label="Receipt (optional)" accept=".pdf,.jpg,.jpeg,.png,.webp" help="PDF or image."/>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'add-expense')">Cancel</button>
                <button type="submit" class="btn-primary">Save spending</button>
            </div>
        </form>
    </x-modal>

    <x-modal name="edit-account" title="Edit account" maxWidth="md">
        <form method="POST" action="{{ route('bank.update', $account) }}" class="space-y-4">
            @csrf @method('PUT')
            <x-form.input name="name" label="Name" :value="$account->name" required/>
            <x-form.input name="bank_name" label="Bank" :value="$account->bank_name" required/>
            <x-form.input name="account_name" label="Account holder" :value="$account->account_name"/>
            <x-form.input name="account_number" label="Account number" :value="$account->account_number" required/>
            <x-form.input name="opening_balance" label="Opening balance" type="number" step="0.01" min="0" :value="$account->opening_balance"/>
            <x-form.select name="status" label="Status" :options="['Active' => 'Active', 'Inactive' => 'Inactive']" :value="$account->status"/>
            <x-form.textarea name="notes" label="Notes" :value="$account->notes"/>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'edit-account')">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
