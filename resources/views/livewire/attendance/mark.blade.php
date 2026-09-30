<div class="space-y-4">
    @if ($flash)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200" role="status">{{ $flash }}</div>
    @endif
    @if ($error)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200" role="alert">{{ $error }}</div>
    @endif

    @if ($activity->charge_fee)
        <div class="card flex flex-wrap items-end gap-3 !p-4">
            <div>
                <label class="label" for="feeDue">Fee due per scout</label>
                <input id="feeDue" type="number" step="0.01" min="0" wire:model="feeDue" class="input w-40">
                @error('feeDue')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <button type="button" wire:click="updateFee" class="btn-secondary">Update fee</button>
            <p class="text-xs text-gray-500">Changing the fee re-prices every class fee for this activity.</p>
        </div>
    @endif

    <div class="card grid gap-3 !p-4 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Search name">
        <select wire:model.live="section" class="input">
            <option value="">Any section</option>
            @foreach (\App\Enums\ScoutSection::cases() as $s)<option value="{{ $s->value }}">{{ $s->value }}</option>@endforeach
        </select>
        <select wire:model.live="statusFilter" class="input">
            <option value="">Any status</option>
            <option value="unmarked">Unmarked</option>
            @foreach (\App\Enums\AttendanceStatus::cases() as $s)<option value="{{ $s->value }}">{{ $s->value }}</option>@endforeach
        </select>
        <div class="flex gap-2">
            <button type="button" wire:click="markAllPresent" class="btn-secondary btn-sm">Mark all present</button>
            <button type="button" wire:click="clear" class="btn-secondary btn-sm">Clear</button>
        </div>
    </div>

    <p class="text-sm text-gray-500">{{ $students->count() }} of {{ $total }} scouts shown. Rows left blank are not saved.</p>

    @if ($total === 0)
        <x-empty message="Nobody on this roster is in your groups."/>
    @else
        <x-table :headers="array_values(array_filter(['Scout', 'Status', 'Remarks', $activity->charge_fee ? 'Paid now' : null]))">
            @foreach ($students as $student)
                <tr wire:key="row-{{ $student->id }}">
                    <td data-label="Scout">
                        <div class="font-medium">{{ $student->name }}</div>
                        <div class="text-xs text-gray-500">{{ $student->section?->value }} · {{ $student->index_number }}</div>
                    </td>
                    <td data-label="Status">
                        <div class="flex flex-wrap justify-end gap-1 md:justify-start">
                            @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                                <label class="cursor-pointer">
                                    <input type="radio" class="peer sr-only" value="{{ $status->value }}" wire:model.live="rows.{{ $student->id }}.status">
                                    <span class="inline-block rounded-md border border-gray-300 px-2 py-1 text-xs peer-checked:border-navy-600 peer-checked:bg-navy-600 peer-checked:text-white dark:border-gray-600">{{ $status->value }}</span>
                                </label>
                            @endforeach
                        </div>
                    </td>
                    <td data-label="Remarks"><input type="text" wire:model="rows.{{ $student->id }}.remarks" class="input !py-1 text-xs" maxlength="255"></td>
                    @if ($activity->charge_fee)
                        <td data-label="Paid now">
                            @if (($rows[$student->id]['status'] ?? '') === 'Excused')
                                <span class="text-xs text-gray-400">Excused — no fee</span>
                            @else
                                <div class="flex items-center justify-end gap-2 md:justify-start">
                                    <select wire:model.live="rows.{{ $student->id }}.payment" class="input !w-28 !py-1 text-xs">
                                        <option value="">—</option>
                                        @foreach ($choices as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select>
                                    @if (($rows[$student->id]['payment'] ?? '') === 'other')
                                        <input type="number" step="0.01" min="0" wire:model="rows.{{ $student->id }}.other" class="input !w-24 !py-1 text-xs" placeholder="Amount">
                                    @endif
                                </div>
                                @if ($fee = $fees->get($student->id))
                                    <div class="mt-1 text-xs text-gray-500">Fee {{ scout_money($fee->amount) }} · paid {{ scout_money($fee->paid_amount) }}</div>
                                @endif
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-table>

        <div class="sticky bottom-0 flex justify-end border-t border-gray-200 bg-gray-50/90 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
            <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">Save register</button>
        </div>
    @endif
</div>
