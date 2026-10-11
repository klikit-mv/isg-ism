<x-app-layout :title="$meta['title'].' report'">
    <x-page-header :title="$meta['title'].' report'" :description="$meta['description']">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn-secondary">All reports</a>
            <a href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" class="btn-secondary" target="_blank" rel="noopener">Print</a>
            <a href="{{ route('reports.export', ['type' => $type] + request()->query() + ['format' => 'xlsx']) }}" class="btn-primary">XLSX</a>
            <a href="{{ route('reports.export', ['type' => $type] + request()->query() + ['format' => 'csv']) }}" class="btn-primary">CSV</a>
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        @if (in_array('q', $meta['filters'], true))<x-form.input name="q" label="Search" :value="request('q')"/>@endif
        @if (in_array('status', $meta['filters'], true))<x-form.select name="status" label="Status" :options="$meta['statuses']" :value="request('status')" placeholder="Any status"/>@endif
        @if (in_array('year', $meta['filters'], true))<x-form.select name="year" label="Year" :options="$years" :value="request('year')" placeholder="Any year"/>@endif
        @if (in_array('method', $meta['filters'], true))<x-form.select name="method" label="Method" :options="$methods" :value="request('method')" placeholder="Any method"/>@endif
        @if (in_array('from', $meta['filters'], true))<x-form.input name="from" label="From" type="date" :value="request('from')"/>@endif
        @if (in_array('to', $meta['filters'], true))<x-form.input name="to" label="To" type="date" :value="request('to')"/>@endif
    </x-filters>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-stat label="Rows" :value="$totals['rows']"/>
        @foreach (['billed' => 'Billed', 'paid' => 'Paid', 'outstanding' => 'Outstanding', 'amount' => 'Amount'] as $key => $label)
            @isset($totals[$key])<x-stat :label="$label" :value="scout_money($totals[$key])" :tone="$key === 'paid' ? 'gold' : 'navy'"/>@endisset
        @endforeach
    </div>

    @if ($rows->isEmpty())
        <x-empty message="No rows match these filters."/>
    @else
        <x-table :headers="$headings">
            @foreach ($rows as $row)
                <tr>
                    @foreach ($reports->mapRow($type, $row) as $index => $cell)
                        <td data-label="{{ $headings[$index] }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $rows->links() }}</div>
    @endif
</x-app-layout>
