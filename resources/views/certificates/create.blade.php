<x-app-layout title="Issue certificate">
    <x-page-header title="Issue a general certificate" description="Choose a template, or an activity whose template to use.">
        <x-slot:actions><a href="{{ route('certificates.index') }}" class="btn-secondary">Back</a></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('certificates.store') }}" class="card max-w-2xl space-y-4">
        @csrf
        <x-form.select name="student" label="Scout" :options="$students" placeholder="Choose a scout" required/>
        <x-form.input name="title" label="Title" required placeholder="e.g. Best Patrol Leader 2026"/>
        <x-form.input name="date_awarded" label="Date awarded" type="date" :value="scout_today()" required/>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.select name="template" label="Template" :options="$templates" placeholder="Choose a template"/>
            <x-form.select name="activity" label="…or the template of an activity" :options="$activities" placeholder="None"/>
        </div>
        <button class="btn-primary">Issue certificate</button>
    </form>
</x-app-layout>
