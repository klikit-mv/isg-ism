<x-app-layout title="Edit activity">
    <x-page-header :title="'Edit '.$activity->name">
        <x-slot:actions>
            <a href="{{ route('activities.index') }}" class="btn-secondary">Back</a>
            @can('delete', $activity)
                <x-confirm :action="route('activities.destroy', $activity)" method="DELETE" label="Delete" size="md" message="Delete this activity? Attendance and fees are kept." confirm="Delete"/>
            @endcan
        </x-slot:actions>
    </x-page-header>
    <form method="POST" action="{{ route('activities.update', $activity) }}" class="card space-y-4">
        @csrf
        @method('PUT')
        @include('activities.partials.form', ['activity' => $activity])
        <button class="btn-primary">Save changes</button>
    </form>
</x-app-layout>
