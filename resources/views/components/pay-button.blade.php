@props(['payable'])
@php
    $status = $payable instanceof \App\Models\Purchase || $payable instanceof \App\Models\EventRegistration ? $payable->payment_status : $payable->status;
    $closed = in_array($status, [\App\Enums\FeeStatus::Paid, \App\Enums\FeeStatus::Void], true)
        || ($payable instanceof \App\Models\Purchase && $payable->purchase_status === \App\Enums\PurchaseStatus::Cancelled)
        || ($payable instanceof \App\Models\EventRegistration && ! $payable->isActive());
@endphp
@unless ($closed)
    <button type="button" class="btn-accent btn-sm" x-data
        x-on:click="$dispatch('pay', { type: @js($payable->payableTypeKey()), id: @js($payable->uuid), amount: @js((string) $payable->outstanding_amount), description: @js($payable->payableDescription().' — outstanding '.scout_money($payable->outstanding_amount)) })">Pay</button>
@endunless
