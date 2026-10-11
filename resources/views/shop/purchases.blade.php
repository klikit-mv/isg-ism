<x-app-layout title="Purchases">
    <x-page-header title="Purchases" description="Orders are confirmed once fully paid. Stock is taken at that moment."/>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Scout or item"/>
        <x-form.select name="payment_status" label="Payment" :options="collect(\App\Enums\FeeStatus::options())->except('Void')->all()" :value="request('payment_status')" placeholder="Any"/>
        <x-form.select name="purchase_status" label="Order status" :options="\App\Enums\PurchaseStatus::options()" :value="request('purchase_status')" placeholder="Any"/>
    </x-filters>

    @if ($purchases->isEmpty())
        <x-empty message="No purchases yet."/>
    @else
        <x-table :headers="[['label' => 'Date', 'sort' => 'date'], ['label' => 'Scout', 'sort' => 'scout'], 'Items', ['label' => 'Total', 'sort' => 'total'], ['label' => 'Paid', 'sort' => 'paid'], ['label' => 'Payment', 'sort' => 'payment'], ['label' => 'Order', 'sort' => 'order'], '']" default-sort="date:desc">
            @foreach ($purchases as $purchase)
                <tr>
                    <td data-label="Date" class="whitespace-nowrap">{{ scout_date($purchase->created_at) }}</td>
                    <td data-label="Scout" class="font-medium">{{ $purchase->student?->name }}</td>
                    <td data-label="Items">
                        @foreach ($purchase->items as $line)
                            <div>{{ $line->quantity }} × {{ $line->item_name_snapshot }}</div>
                        @endforeach
                    </td>
                    <td data-label="Total">{{ scout_money($purchase->total_amount) }}</td>
                    <td data-label="Paid">{{ scout_money($purchase->paid_amount) }}</td>
                    <td data-label="Payment"><x-badge :value="$purchase->payment_status"/></td>
                    <td data-label="Order">
                        <x-badge :value="$purchase->purchase_status"/>
                        @if ($purchase->delivered_at)<div class="mt-1 text-xs text-gray-500">To {{ $purchase->recipient }} on {{ scout_date($purchase->delivered_at) }}</div>@endif
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <div class="flex flex-wrap justify-end gap-1">
                            <x-pay-button :payable="$purchase"/>
                            @if ($canDeliver && $purchase->purchase_status === \App\Enums\PurchaseStatus::Confirmed)
                                <form method="POST" action="{{ route('purchases.ready', $purchase) }}">@csrf<button class="btn-secondary btn-sm">Ready</button></form>
                            @endif
                            @if ($canDeliver && $purchase->isFullyPaid() && in_array($purchase->purchase_status, [\App\Enums\PurchaseStatus::Confirmed, \App\Enums\PurchaseStatus::ReadyForCollection], true))
                                <x-confirm :action="route('purchases.deliver', $purchase)" label="Deliver" variant="primary" title="Deliver purchase" message="Who collected it?" confirm="Mark delivered">
                                    <x-form.input name="recipient" label="Recipient" :value="$purchase->student?->name" :id="'recipient-'.$purchase->uuid"/>
                                </x-confirm>
                            @endif
                            @if ($purchase->purchase_status === \App\Enums\PurchaseStatus::PendingPayment && ! \App\Support\Money::isPositive($purchase->paid_amount))
                                <x-confirm :action="route('purchases.cancel', $purchase)" label="Cancel" variant="secondary" message="Cancel this order?" confirm="Cancel order"/>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $purchases->links() }}</div>
    @endif

    <x-payment-modal/>
</x-app-layout>
