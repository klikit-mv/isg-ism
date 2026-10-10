{{--
    Headers are plain labels or ['label' => ..., 'sort' => key]. A heading with a sort key links to the same page ordered
    by that column (the controller does the ordering, so every page of a long list is covered). The other headings sort
    the rows on screen when clicked. default-sort="key:dir" marks the order a list opens in.
--}}
@props(['headers' => [], 'defaultSort' => null])

@php
    [$activeKey, $activeDir] = request()->filled('sort')
        ? [(string) request()->query('sort'), strtolower((string) request()->query('dir')) === 'desc' ? 'desc' : 'asc']
        : (($defaultSort && str_contains($defaultSort, ':')) ? explode(':', $defaultSort, 2) : [null, 'asc']);
@endphp

<div class="md:overflow-hidden md:rounded-xl md:border md:border-gray-200 md:bg-white md:shadow-sm md:dark:border-gray-700 md:dark:bg-gray-800">
    <div class="md:overflow-x-auto"
        x-data="{
            column: null, direction: 1, enabled: false,
            init() { this.enabled = $el.querySelectorAll('tbody td[colspan]').length === 0 && ! $el.closest('[wire\\:id]'); },
            value(row, index) {
                const cell = row.cells[index];
                if (! cell) return '';
                if (cell.dataset.sort !== undefined) return cell.dataset.sort;
                const field = cell.querySelector('select, input:not([type=checkbox]):not([type=radio]):not([type=hidden])');
                return (field ? field.value : cell.textContent).replace(/\s+/g, ' ').trim();
            },
            number(text) {
                const cleaned = text.replace(/[A-Za-zހ-޿ ,]/g, '');
                return cleaned !== '' && /^-?\d+(\.\d+)?$/.test(cleaned) ? parseFloat(cleaned) : null;
            },
            date(text) {
                const m = text.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})(?: (\d{1,2}):(\d{2}))?/);
                return m ? new Date(+m[3], +m[2] - 1, +m[1], +(m[4] || 0), +(m[5] || 0)).getTime() : null;
            },
            sortBy(index) {
                if (! this.enabled) return;
                this.direction = this.column === index ? -this.direction : 1;
                this.column = index;
                const body = $el.querySelector('tbody');
                const rows = [...body.querySelectorAll(':scope > tr')];
                const empty = (t) => t === '' || t === '—' || t === '-';
                rows.map((row, position) => ({ row, position, text: this.value(row, index) }))
                    .sort((a, b) => {
                        if (empty(a.text) !== empty(b.text)) return empty(a.text) ? 1 : -1;
                        const da = this.date(a.text), db = this.date(b.text);
                        const na = this.number(a.text), nb = this.number(b.text);
                        let result;
                        if (da !== null && db !== null) result = da - db;
                        else if (na !== null && nb !== null) result = na - nb;
                        else result = a.text.localeCompare(b.text, undefined, { numeric: true, sensitivity: 'base' });
                        return result * this.direction || a.position - b.position;
                    })
                    .forEach((item) => body.appendChild(item.row));
            },
        }">
        <table {{ $attributes->merge(['class' => 'rtable']) }}>
            @if ($headers)
                <thead>
                    <tr>
                        @foreach ($headers as $index => $header)
                            @php
                                $label = is_array($header) ? (string) ($header['label'] ?? '') : (string) $header;
                                $key = is_array($header) ? ($header['sort'] ?? null) : null;
                                $isActive = $key !== null && $activeKey === $key;
                                $nextDir = $isActive && $activeDir === 'asc' ? 'desc' : 'asc';
                            @endphp
                            @if ($label === '')
                                <th scope="col"></th>
                            @elseif ($key !== null)
                                <th scope="col" aria-sort="{{ $isActive ? ($activeDir === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir, 'page' => null]) }}" class="inline-flex items-center gap-1 hover:text-navy-700 dark:hover:text-navy-300" title="Sort by {{ $label }}">
                                        {{ $label }}
                                        <span aria-hidden="true" class="text-[10px] leading-none {{ $isActive ? 'text-navy-700 dark:text-navy-300' : 'text-gray-300 dark:text-gray-600' }}">{{ $isActive ? ($activeDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                                    </a>
                                </th>
                            @else
                                <th scope="col">
                                    <button type="button" class="inline-flex items-center gap-1 uppercase tracking-wide hover:text-navy-700 dark:hover:text-navy-300" x-bind:class="enabled ? 'cursor-pointer' : 'cursor-default'" x-on:click="sortBy({{ $index }})" x-bind:title="enabled ? 'Sort by {{ addslashes($label) }}' : ''">
                                        {{ $label }}
                                        <span aria-hidden="true" class="text-[10px] leading-none" x-show="enabled" x-bind:class="column === {{ $index }} ? 'text-navy-700 dark:text-navy-300' : 'text-gray-300 dark:text-gray-600'" x-text="column === {{ $index }} ? (direction === 1 ? '▲' : '▼') : '↕'">↕</span>
                                    </button>
                                </th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
