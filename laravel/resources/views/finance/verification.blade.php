<x-app-layout title="Payment verification">
    <x-page-header title="Payment verification" description="Online payments waiting for a check, oldest first."/>

    @if ($payments->isEmpty())
        <x-empty message="Nothing is waiting for verification."/>
    @else
        <div class="space-y-4">
            @foreach ($payments as $payment)
                <div class="card flex flex-col gap-4 md:flex-row">
                    <div class="md:w-48">
                        @if ($payment->proof?->isImage())
                            <a href="{{ route('payments.proof', $payment) }}" target="_blank" rel="noopener"><img src="{{ route('payments.proof', $payment) }}" alt="Payment proof" class="max-h-48 w-full rounded-lg border object-contain dark:border-gray-700"></a>
                        @elseif ($payment->proof)
                            <a href="{{ route('payments.proof', $payment) }}" target="_blank" rel="noopener" class="btn-secondary w-full">Open proof (PDF)</a>
                        @else
                            <span class="text-sm text-gray-500">No proof</span>
                        @endif
                    </div>
                    <div class="flex-1 space-y-1 text-sm">
                        <div class="text-lg font-semibold">{{ scout_money($payment->amount) }}</div>
                        <div>{{ $payment->typeLabel() }} · {{ $payment->payable?->payableDescription() }}</div>
                        <div>Scout: {{ $payment->student?->name ?? '—' }}</div>
                        <div class="text-gray-500">Submitted by {{ $payment->submitter?->name }} on {{ scout_datetime($payment->submitted_at) }}</div>
                        <div class="text-gray-500">Outstanding on this record: {{ scout_money($payment->payable?->outstanding_amount) }}</div>
                    </div>
                    <div class="flex flex-row gap-2 md:flex-col">
                        <form method="POST" action="{{ route('payments.approve', $payment) }}">@csrf<button class="btn-accent w-full">Approve</button></form>
                        <x-confirm :action="route('payments.reject', $payment)" label="Reject" size="md" title="Reject payment" message="The submitter will see this reason." confirm="Reject payment">
                            <x-form.input name="reason" label="Reason" required maxlength="255"/>
                        </x-confirm>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $payments->links() }}</div>
    @endif
</x-app-layout>
