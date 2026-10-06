<x-app-layout title="Bank">
    <x-page-header title="Bank account" description="Set up the group's bank account to record deposits, spending and verified online payments."/>

    <div class="card max-w-xl">
        <form method="POST" action="{{ route('bank.store') }}" class="space-y-4">
            @csrf
            <x-form.input name="name" label="Name" placeholder="General fund" required/>
            <x-form.input name="bank_name" label="Bank" required/>
            <x-form.input name="account_name" label="Account holder"/>
            <x-form.input name="account_number" label="Account number" required/>
            <x-form.input name="opening_balance" label="Money already in the account" type="number" step="0.01" min="0" value="0"/>
            <x-form.input name="online_from" label="Count online payments verified from" type="date" :value="now()->toDateString()" help="Verified online payments from this date are added to the balance automatically."/>
            <x-form.textarea name="notes" label="Notes"/>
            <div class="flex justify-end"><button type="submit" class="btn-primary">Create account</button></div>
        </form>
    </div>
</x-app-layout>
