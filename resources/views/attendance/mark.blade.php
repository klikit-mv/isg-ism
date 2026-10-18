<x-app-layout :title="'Attendance · '.$activity->name">
    <x-page-header :title="$activity->name" :description="scout_date($activity->date).' · '.$activity->targetSummary()">
        <x-slot:actions>
            <a href="{{ route('attendance.index') }}" class="btn-secondary">All activities</a>
        </x-slot:actions>
    </x-page-header>
    <livewire:attendance.mark :activity="$activity"/>
</x-app-layout>
