<x-app-layout title="Badge requests">
    <x-page-header title="Badge requests" description="Requests for proficiency and other badges.">
        <x-slot:actions>
            @if ($canBulk)
                <button type="button" class="btn-accent" x-data x-on:click="$dispatch('open-modal', 'bulk-badges')">Bulk badge request</button>
            @endif
            <a href="{{ route('badge-requests.create') }}" class="btn-primary">Request a badge</a>
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Scout, badge or request id"/>
        <x-form.select name="status" label="Status" :options="\App\Enums\BadgeRequestStatus::options()" :value="request('status')" placeholder="Any status"/>
    </x-filters>

    @if ($requests->isEmpty())
        <x-empty message="No badge requests yet."/>
    @else
        @if ($canBulk)
            <form id="approve-selected" method="POST" action="{{ route('badge-requests.approve-selected') }}">@csrf</form>
            <div class="mb-2 flex flex-wrap items-center justify-end gap-2" x-data="{ all: false }">
                <label class="mr-auto inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="checkbox" class="rounded border-gray-300 text-navy-600" x-model="all"
                        x-on:change="document.querySelectorAll('input[form=approve-selected][type=checkbox]').forEach((box) => box.checked = all)">
                    Select all waiting requests on this page
                </label>
                <button form="approve-selected" class="btn-accent btn-sm">Approve selected</button>
            </div>
        @endif
        <x-table :headers="array_merge($canBulk ? [''] : [], [['label' => 'Request', 'sort' => 'request'], ['label' => 'Scout', 'sort' => 'scout'], ['label' => 'Badge', 'sort' => 'badge'], ['label' => 'Status', 'sort' => 'status'], ['label' => 'Certificate', 'sort' => 'certificate'], ['label' => 'Requested', 'sort' => 'requested'], ''])" default-sort="requested:desc">
            @foreach ($requests as $item)
                <tr>
                    @if ($canBulk)
                        <td>@if ($item->status === \App\Enums\BadgeRequestStatus::Requested)<input form="approve-selected" type="checkbox" name="requests[]" value="{{ $item->uuid }}" class="rounded border-gray-300 text-navy-600" aria-label="Select {{ $item->request_id }}">@endif</td>
                    @endif
                    <td data-label="Request" class="font-mono text-xs">{{ $item->request_id }}</td>
                    <td data-label="Scout">{{ $item->student_name }}</td>
                    <td data-label="Badge">{{ $item->badge_name }}</td>
                    <td data-label="Status"><x-badge :value="$item->status"/></td>
                    <td data-label="Certificate" class="font-mono text-xs">{{ $item->certificate_number ?: '—' }}</td>
                    <td data-label="Requested">{{ scout_date($item->created_at) }}</td>
                    <td class="text-right"><a href="{{ route('badge-requests.show', $item) }}" class="link">Open</a></td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $requests->links() }}</div>
    @endif

    @if ($canBulk)
        <x-modal name="bulk-badges" title="Bulk badge request" maxWidth="2xl">
            <div
                x-data="{
                    step: 1, busy: false, error: '', data: @js($bulk), url: @js(route('badge-requests.bulk')),
                    badge: '', search: '', picked: [], approve: true, date: @js(now()->toDateString()), template: '', result: null,
                    get chosenBadge() { return this.data.badges.find((b) => b.id === this.badge) || null; },
                    get scouts() {
                        const q = this.search.trim().toLowerCase();
                        return this.data.students.filter((s) => (! this.chosenBadge || ! this.chosenBadge.section || s.section === this.chosenBadge.section)
                            && (q === '' || (s.name + ' ' + (s.index || '')).toLowerCase().includes(q)));
                    },
                    pickAll() { this.picked = [...new Set([...this.picked, ...this.scouts.map((s) => s.id)])]; },
                    clearAll() { this.picked = []; },
                    chooseBadge() { const ids = new Set(this.scouts.map((s) => s.id)); this.picked = this.picked.filter((id) => ids.has(id)); },
                    async run() {
                        this.busy = true; this.error = '';
                        try {
                            const res = await fetch(this.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                body: JSON.stringify({ badge: this.badge, students: this.picked, approve: this.approve, date_awarded: this.approve ? this.date : null, template: this.approve && this.template ? this.template : null }) });
                            const body = await res.json().catch(() => ({}));
                            if (! res.ok) { this.error = body.message || 'Something went wrong. Please try again.'; return; }
                            this.result = body; this.step = 3;
                        } catch (e) { this.error = 'Could not reach the server. Please try again.'; } finally { this.busy = false; }
                    },
                    finish() { this.$dispatch('close-modal', 'bulk-badges'); window.location.reload(); },
                }"
                class="space-y-4"
            >
                <ol class="flex gap-2 text-xs font-medium text-gray-500">
                    <li :class="step === 1 ? 'text-navy-700 dark:text-navy-300' : ''">1. Badge and scouts</li><li>›</li>
                    <li :class="step === 2 ? 'text-navy-700 dark:text-navy-300' : ''">2. Approve and generate</li><li>›</li>
                    <li :class="step === 3 ? 'text-navy-700 dark:text-navy-300' : ''">3. Summary</li>
                </ol>
                <p x-show="error" x-text="error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-900/30 dark:text-rose-200" x-cloak></p>

                {{-- Step 1 --}}
                <div x-show="step === 1" class="space-y-3">
                    <div x-data="{ open: false, q: '', get shown() { const q = this.q.trim().toLowerCase(); return q === '' ? data.badges : data.badges.filter((b) => (b.name + ' ' + (b.section || '')).toLowerCase().includes(q)); }, get label() { const b = data.badges.find((x) => x.id === badge); return b ? b.name + (b.section ? ' — ' + b.section : '') : ''; }, pick(id) { badge = id; open = false; q = ''; chooseBadge(); } }" x-on:click.outside="open = false" x-on:keydown.escape.stop="open = false">
                        <label for="bulk-badge" class="label">Badge</label>
                        <div class="relative">
                            <input type="text" id="bulk-badge" readonly class="input cursor-pointer pr-9" placeholder="Choose a badge" x-bind:value="label" x-on:click="open = ! open; if (open) $nextTick(() => $refs.find.focus())" autocomplete="off" aria-haspopup="listbox">
                            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                        <div x-show="open" x-cloak class="mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            <div class="border-b border-gray-100 p-2 dark:border-gray-700"><input type="search" x-ref="find" x-model="q" class="input !py-1.5 text-sm" x-bind:placeholder="'Type to search… (' + data.badges.length + ' badges)'" autocomplete="off" x-on:keydown.enter.prevent="shown.length && pick(shown[0].id)"></div>
                            <ul class="max-h-60 overflow-y-auto py-1 text-sm" role="listbox">
                                <template x-for="b in shown" :key="b.id"><li><button type="button" role="option" class="block w-full px-3 py-1.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700" x-bind:class="b.id === badge ? 'bg-navy-50 font-medium text-navy-800 dark:bg-navy-900/40 dark:text-navy-200' : ''" x-on:click="pick(b.id)" x-text="b.name + (b.section ? ' — ' + b.section : '')"></button></li></template>
                                <li x-show="shown.length === 0" class="px-3 py-2 text-gray-500">No matches</li>
                            </ul>
                        </div>
                    </div>
                    <div x-show="badge !== ''" class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="search" x-model="search" class="input !w-auto flex-1" placeholder="Search scouts">
                            <button type="button" class="btn-secondary btn-sm" x-on:click="pickAll()">Select all shown</button>
                            <button type="button" class="btn-secondary btn-sm" x-on:click="clearAll()">Clear</button>
                        </div>
                        <p class="text-xs text-gray-500"><span x-text="picked.length"></span> selected<span x-show="chosenBadge && chosenBadge.section"> · showing <span x-text="chosenBadge ? chosenBadge.section : ''"></span> scouts only</span></p>
                        <div class="max-h-64 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            <template x-for="s in scouts" :key="s.id">
                                <label class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <input type="checkbox" class="rounded border-gray-300 text-navy-600" :value="s.id" x-model="picked">
                                    <span class="min-w-0 flex-1 truncate" x-text="s.name"></span>
                                    <span class="text-xs text-gray-500" x-text="s.section"></span>
                                </label>
                            </template>
                            <p x-show="scouts.length === 0" class="px-3 py-3 text-sm text-gray-500">No scouts to show.</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'bulk-badges')">Cancel</button>
                        <button type="button" class="btn-primary" x-bind:disabled="badge === '' || picked.length === 0" x-on:click="step = 2">Next</button>
                    </div>
                </div>

                {{-- Step 2 --}}
                <div x-show="step === 2" class="space-y-3" x-cloak>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Requesting <strong x-text="chosenBadge ? chosenBadge.name : ''"></strong> for <strong x-text="picked.length"></strong> scout(s).</p>
                    <label class="flex items-start gap-2 text-sm"><input type="radio" class="mt-1" :value="true" x-model.boolean="approve"><span><strong>Approve and generate certificates now</strong><br><span class="text-xs text-gray-500">Each request is approved and its certificate is generated with the date below.</span></span></label>
                    <label class="flex items-start gap-2 text-sm"><input type="radio" class="mt-1" :value="false" x-model.boolean="approve"><span><strong>Only send the requests</strong><br><span class="text-xs text-gray-500">They wait for approval; no certificates yet.</span></span></label>
                    <div x-show="approve" class="grid gap-3 sm:grid-cols-2">
                        <div><label for="bulk-date" class="label">Date awarded</label><input id="bulk-date" type="date" class="input" x-model="date"></div>
                        <div><label for="bulk-template" class="label">Template</label>
                            <select id="bulk-template" class="input" x-model="template">
                                <option value="">The badge's template (default)</option>
                                <template x-for="t in data.templates" :key="t.id"><option :value="t.id" x-text="t.name"></option></template>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-secondary" x-on:click="step = 1">Back</button>
                        <button type="button" class="btn-primary" x-bind:disabled="busy || (approve && date === '')" x-on:click="run()"><span x-text="busy ? 'Working…' : (approve ? 'Approve and generate' : 'Send requests')"></span></button>
                    </div>
                </div>

                {{-- Step 3 --}}
                <div x-show="step === 3" class="space-y-3" x-cloak>
                    <template x-if="result">
                        <div class="space-y-3">
                            <p class="text-sm"><strong x-text="result.badge"></strong><span x-show="result.approve"> · awarded <span x-text="result.date_awarded"></span></span></p>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 dark:bg-gray-700">Total <strong x-text="result.counts.total"></strong></span>
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300" x-show="result.approve">Certificates <strong x-text="result.counts.generated"></strong></span>
                                <span class="rounded-full bg-sky-100 px-2.5 py-1 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300" x-show="! result.approve">Requested <strong x-text="result.counts.requested"></strong></span>
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" x-show="result.counts.approved > 0">Approved only <strong x-text="result.counts.approved"></strong></span>
                                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300" x-show="result.counts.failed > 0">Failed <strong x-text="result.counts.failed"></strong></span>
                            </div>
                            <div class="max-h-64 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/40"><tr><th class="px-3 py-2">Scout</th><th class="px-3 py-2">Result</th><th class="px-3 py-2">Certificate</th></tr></thead>
                                    <tbody>
                                        <template x-for="row in result.rows" :key="row.student">
                                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                                <td class="px-3 py-2" x-text="row.student"></td>
                                                <td class="px-3 py-2" :class="row.status === 'failed' ? 'text-rose-700 dark:text-rose-300' : ''" x-text="row.message"></td>
                                                <td class="px-3 py-2 font-mono text-xs" x-text="row.certificate || '—'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                    <div class="flex justify-end"><button type="button" class="btn-primary" x-on:click="finish()">Close</button></div>
                </div>
            </div>
        </x-modal>
    @endif
</x-app-layout>
