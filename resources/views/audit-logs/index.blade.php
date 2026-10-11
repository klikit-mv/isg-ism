<x-app-layout title="Audit logs">
    <x-page-header title="Audit logs" description="Append-only record of financial, membership, attendance, import and sign-in events."/>

    <x-filters>
        <x-form.input name="action" label="Action starts with" :value="request('action')" placeholder="payment."/>
        <x-form.select name="entity" label="Entity" :options="$entities" :value="request('entity')" placeholder="Any"/>
        <x-form.input name="from" label="From" type="date" :value="request('from')"/>
        <x-form.input name="to" label="To" type="date" :value="request('to')"/>
    </x-filters>

    @if ($logs->isEmpty())
        <x-empty message="No audit entries match these filters."/>
    @else
        <x-table :headers="[['label' => 'When', 'sort' => 'when'], ['label' => 'Action', 'sort' => 'action'], ['label' => 'Entity', 'sort' => 'entity'], ['label' => 'By', 'sort' => 'by'], 'Details']" default-sort="when:desc">
            @foreach ($logs as $log)
                <tr>
                    <td data-label="When" class="whitespace-nowrap">{{ scout_datetime($log->created_at) }}</td>
                    <td data-label="Action" class="font-mono text-xs">{{ $log->action }}</td>
                    <td data-label="Entity" class="text-xs">{{ $log->entity_type }} <span class="block font-mono text-gray-400">{{ \Illuminate\Support\Str::limit((string) $log->entity_id, 13) }}</span></td>
                    <td data-label="By">{{ $log->actor?->name ?? 'System' }}</td>
                    <td data-label="Details" class="max-w-md break-words font-mono text-xs text-gray-500">{{ $log->details ? json_encode($log->details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '' }}</td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</x-app-layout>
