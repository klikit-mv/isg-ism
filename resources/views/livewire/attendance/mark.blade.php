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

    <div class="card flex flex-wrap items-center gap-2 !p-3 text-xs" data-testid="live-totals" aria-live="polite">
        <span class="font-semibold uppercase tracking-wide text-gray-500">Register now</span>
        <span class="rounded-full bg-emerald-100 px-2.5 py-1 font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">Present {{ $live['counts']['Present'] }}</span>
        <span class="rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Late {{ $live['counts']['Late'] }}</span>
        <span class="rounded-full bg-rose-100 px-2.5 py-1 font-medium text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">Absent {{ $live['counts']['Absent'] }}</span>
        <span class="rounded-full bg-sky-100 px-2.5 py-1 font-medium text-sky-800 dark:bg-sky-900/40 dark:text-sky-300">Excused {{ $live['counts']['Excused'] }}</span>
        <span class="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">Need to report {{ $live['unmarked'] }}</span>
        @if ($activity->charge_fee)
            <span class="ml-auto rounded-full bg-gold-100 px-2.5 py-1 font-semibold text-gold-800 dark:bg-gold-900/40 dark:text-gold-300">Collected {{ scout_money($live['collected']) }}</span>
            <span class="rounded-full bg-rose-50 px-2.5 py-1 font-medium text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Not paid {{ $live['not_paid'] }}</span>
        @endif
    </div>

    @if ($total === 0)
        <x-empty message="Nobody on this roster is in your groups."/>
    @else
        <x-table :headers="array_values(array_filter(['Scout', 'Status', 'Remarks', $activity->charge_fee ? 'Paid now' : null]))">
            @foreach ($sections as $section)
                @if (count($sections) > 1 || $section['title'] !== 'No sub-group')
                    <tr wire:key="section-{{ $loop->index }}" class="bg-gray-50 dark:bg-gray-900/40">
                        <td colspan="{{ $activity->charge_fee ? 4 : 3 }}" class="!py-2 text-sm font-semibold text-navy-800 dark:text-navy-200">{{ $section['title'] }} <span class="font-normal text-gray-500">({{ $section['students']->count() }})</span></td>
                    </tr>
                @endif
                @foreach ($section['students'] as $student)
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
                                    <span class="inline-block rounded-md border px-2 py-1 text-xs font-medium {{ $status->buttonClasses() }}">{{ $status->value }}</span>
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
                                        <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="rows.{{ $student->id }}.other" class="input !w-24 !py-1 text-xs" placeholder="Amount">
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
            @endforeach
        </x-table>

        <div class="sticky bottom-0 flex justify-end border-t border-gray-200 bg-gray-50/90 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
            <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">Save register</button>
        </div>
    @endif

    @if ($summary)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" role="dialog" aria-modal="true" aria-labelledby="attendance-summary-title" wire:keydown.escape.window="closeSummary" data-testid="attendance-summary">
            <div class="fixed inset-0 bg-gray-900/60" wire:click="closeSummary"></div>
            <div class="relative mx-auto mt-10 w-full rounded-xl bg-white shadow-xl dark:bg-gray-800 sm:max-w-lg">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div>
                        <h2 id="attendance-summary-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Attendance saved</h2>
                        <p class="text-xs text-gray-500">{{ $summary['activity'] }} · {{ $summary['date'] }}</p>
                    </div>
                    <button type="button" class="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" wire:click="closeSummary" aria-label="Close">&times;</button>
                </div>
                <div class="space-y-5 px-5 py-4">
                    <section>
                        <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Attendance <span class="font-normal text-gray-400">({{ $summary['total'] }} scouts)</span></h3>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <div class="rounded-lg bg-emerald-50 p-3 dark:bg-emerald-900/30"><div class="text-xs text-emerald-700 dark:text-emerald-300">Present</div><div class="text-xl font-bold text-emerald-800 dark:text-emerald-200">{{ $summary['counts']['Present'] }}</div></div>
                            <div class="rounded-lg bg-amber-50 p-3 dark:bg-amber-900/30"><div class="text-xs text-amber-700 dark:text-amber-300">Late</div><div class="text-xl font-bold text-amber-800 dark:text-amber-200">{{ $summary['counts']['Late'] }}</div></div>
                            <div class="rounded-lg bg-rose-50 p-3 dark:bg-rose-900/30"><div class="text-xs text-rose-700 dark:text-rose-300">Absent</div><div class="text-xl font-bold text-rose-800 dark:text-rose-200">{{ $summary['counts']['Absent'] }}</div></div>
                            <div class="rounded-lg bg-sky-50 p-3 dark:bg-sky-900/30"><div class="text-xs text-sky-700 dark:text-sky-300">Excused</div><div class="text-xl font-bold text-sky-800 dark:text-sky-200">{{ $summary['counts']['Excused'] }}</div></div>
                            <div class="rounded-lg bg-gray-100 p-3 sm:col-span-2 dark:bg-gray-700"><div class="text-xs text-gray-600 dark:text-gray-300">Need to report (not marked)</div><div class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ $summary['unmarked'] }}</div></div>
                        </div>
                    </section>
                    @if ($summary['fee'])
                        <section>
                            <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Fee collection</h3>
                            <dl class="divide-y divide-gray-100 rounded-lg border border-gray-200 text-sm dark:divide-gray-700 dark:border-gray-700">
                                <div class="flex justify-between px-3 py-2"><dt class="text-gray-500">Fees due</dt><dd class="font-medium">{{ scout_money($summary['fee']['due']) }}</dd></div>
                                <div class="flex justify-between px-3 py-2"><dt class="text-gray-500">Sub total collected <span class="text-xs text-gray-400">({{ $summary['fee']['paid_count'] }} scouts paid)</span></dt><dd class="font-semibold text-emerald-700 dark:text-emerald-300">{{ scout_money($summary['fee']['collected']) }}</dd></div>
                                <div class="flex justify-between px-3 py-2"><dt class="text-gray-500">Sub total not paid <span class="text-xs text-gray-400">({{ $summary['fee']['not_paid_count'] }} scouts)</span></dt><dd class="font-semibold text-rose-700 dark:text-rose-300">{{ scout_money($summary['fee']['not_paid_amount']) }}</dd></div>
                            </dl>
                        </section>
                    @endif
                </div>
                <div class="flex justify-end border-t border-gray-200 px-5 py-3 dark:border-gray-700"><button type="button" class="btn-primary" wire:click="closeSummary">Close</button></div>
            </div>
        </div>
    @endif
</div>
