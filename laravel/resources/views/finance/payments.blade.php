<x-app-layout title="Payments">
    <x-page-header title="Payments" description="Online and cash payments."/>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Payment id, scout or National ID"/>
        <x-form.select name="status" label="Status" :options="\App\Enums\PaymentStatus::options()" :value="request('status')" placeholder="Any status"/>
        <x-form.select name="method" label="Method" :options="\App\Enums\PaymentMethod::options()" :value="request('method')" placeholder="Any method"/>
    </x-filters>

    @if ($payments->isEmpty())
        <x-empty message="No payments yet."/>
    @else
        <x-table :headers="['Submitted', 'For', 'Scout', 'Amount', 'Method', 'Status', 'Verified by', '']">
            @foreach ($payments as $payment)
                <tr>
                    <td data-label="Submitted" class="whitespace-nowrap">{{ scout_datetime($payment->submitted_at) }}</td>
                    <td data-label="For">{{ $payment->typeLabel() }}@if ($payment->source === 'roster') <span class="text-xs text-gray-400">(roster)</span>@endif</td>
                    <td data-label="Scout">{{ $payment->student?->name ?? $payment->submitter?->name }}</td>
                    <td data-label="Amount">{{ scout_money($payment->amount) }}</td>
                    <td data-label="Method">{{ $payment->method->label() }}</td>
                    <td data-label="Status">
                        <x-badge :value="$payment->status"/>
                        @if ($payment->rejection_reason)<div class="mt-1 text-xs text-rose-600">{{ $payment->rejection_reason }}</div>@endif
                    </td>
                    <td data-label="Verified by">{{ $payment->verifier?->name ?? '—' }}</td>
                    <td class="text-right">@if ($payment->proof)<a href="{{ route('payments.proof', $payment) }}" class="link" target="_blank" rel="noopener">Proof</a>@endif</td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $payments->links() }}</div>
    @endif
</x-app-layout>
