@php $editing = $record->exists; @endphp
<x-app-layout :title="$editing ? 'Edit leadership record' : 'Add leadership record'">
    <x-page-header :title="$editing ? 'Edit leadership record' : 'Add leadership record'" description="Scout, post, patrol and start date are printed on the certificate, so all four are required. The end date is kept for the record but never printed.">
        <x-slot:actions><a href="{{ $editing ? route('leadership.show', $record) : route('leadership.index') }}" class="btn-secondary">Cancel</a></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('leadership.update', $record) : route('leadership.store') }}" class="card max-w-2xl space-y-4">
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-form.select name="student_id" label="Scout" :options="$students" :value="$record->student_id" placeholder="Choose a scout" required/>
        <x-form.input name="post" label="Post" :value="$record->post" placeholder="Patrol Leader, Second, Troop Leader…" required/>
        <x-form.input name="patrol_or_six" label="Patrol or six" :value="$record->patrol_or_six" required/>
        <x-form.input name="troop_or_group" label="Troop or group" :value="$record->troop_or_group"/>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="start_date" label="Start date" type="date" :value="$record->start_date?->format('Y-m-d')" required/>
            <x-form.input name="end_date" label="End date (optional)" type="date" :value="$record->end_date?->format('Y-m-d')"/>
        </div>
        <button class="btn-primary">Save</button>
    </form>
</x-app-layout>
