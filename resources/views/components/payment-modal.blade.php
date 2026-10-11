{{-- One payment modal per page; rows open it with $dispatch('pay', {type, id, amount, description}). --}}
@php
    $settings = app(\App\Services\SettingsService::class);
    $canCash = app(\App\Services\PaymentService::class)->canRecordCash(auth()->user());
    $mimes = $settings->proofMimes();
@endphp

<div x-data="{ open: false, bank: false, type: '', id: '', amount: '', description: '', method: 'online' }"
     x-on:pay.window="type = $event.detail.type; id = $event.detail.id; amount = $event.detail.amount; description = $event.detail.description; method = 'online'; open = true"
     x-on:keydown.escape.window="open = false; bank = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60" x-on:click="open = false"></div>
        <div class="relative mx-auto mt-10 w-full max-w-lg rounded-xl bg-white shadow-xl dark:bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold">Make a payment</h2>
                <button type="button" class="text-gray-400 hover:text-gray-600" x-on:click="open = false" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="{{ route('payments.store') }}" enctype="multipart/form-data" class="space-y-4 px-5 py-4">
                @csrf
                <input type="hidden" name="payable_type" :value="type">
                <input type="hidden" name="payable_id" :value="id">
                <p class="text-sm text-gray-600 dark:text-gray-300" x-text="description"></p>
                <div>
                    <label class="label" for="pay_amount">Amount ({{ config('scout.currency') }})</label>
                    <input id="pay_amount" name="amount" type="number" step="0.01" min="0.01" class="input" x-model="amount" required>
                </div>
                <div>
                    <label class="label" for="pay_method">Method</label>
                    <select id="pay_method" name="method" class="input" x-model="method">
                        <option value="online">Online transfer (upload proof)</option>
                        @if ($canCash)
                            <option value="cash">Cash received by staff</option>
                        @endif
                    </select>
                </div>
                <div x-show="method === 'online'" class="space-y-2">
                    <button type="button" class="link text-sm" x-on:click="bank = true">Show bank details</button>
                    <x-form.file-drop name="proof" label="Proof of payment" accept=".{{ implode(',.', $mimes) }}" help="{{ strtoupper(implode(', ', $mimes)) }} up to {{ round($settings->proofMaxKb() / 1024, 1) }} MB."/>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" x-on:click="open = false">Cancel</button>
                    <button type="submit" class="btn-primary">Submit payment</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="bank" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center px-4" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/40" x-on:click="bank = false"></div>
        <div class="relative w-full max-w-sm rounded-xl bg-white p-5 shadow-xl dark:bg-gray-800">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-semibold">Bank details</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" x-on:click="bank = false" aria-label="Close">&times;</button>
            </div>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-gray-500">Bank</dt><dd>{{ $settings->bankName() ?: 'Not set' }}</dd></div>
                <div><dt class="text-gray-500">Account name</dt><dd>{{ $settings->accountName() ?: 'Not set' }}</dd></div>
                <div>
                    <dt class="text-gray-500">Account number</dt>
                    <dd class="flex items-center gap-2">
                        <span class="font-mono">{{ $settings->accountNumber() ?: 'Not set' }}</span>
                        @if ($settings->accountNumber())
                            <button type="button" class="btn-secondary btn-sm" x-on:click="window.copyText(@js($settings->accountNumber()), $el)">Copy</button>
                        @endif
                    </dd>
                </div>
                @if ($settings->paymentInstructions())
                    <div class="whitespace-pre-line pt-2 text-gray-600 dark:text-gray-300">{{ $settings->paymentInstructions() }}</div>
                @endif
            </dl>
        </div>
    </div>
</div>
