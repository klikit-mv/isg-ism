<x-app-layout :title="'Rover attendance · '.$activity->name">
    <x-page-header :title="'Rovers · '.$activity->name" :description="scout_date($activity->date)">
        <x-slot:actions>
            <a href="{{ route('rover-attendance.index') }}" class="btn-secondary">All activities</a>
        </x-slot:actions>
    </x-page-header>
    <livewire:attendance.rover-mark :activity="$activity"/>
</x-app-layout>
