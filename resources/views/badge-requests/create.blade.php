<x-app-layout title="Request a badge">
    <x-page-header title="Request a badge" description="A leader reviews each request before a certificate is generated.">
        <x-slot:actions><a href="{{ route('badge-requests.index') }}" class="btn-secondary">Back</a></x-slot:actions>
    </x-page-header>

    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 !p-4">
        <x-form.select name="section" label="Filter scouts by section" :options="\App\Enums\ScoutSection::options()" :value="request('section')" placeholder="All sections"/>
        <button class="btn-secondary btn-sm">Filter</button>
    </form>

    <form method="POST" action="{{ route('badge-requests.store') }}" class="card max-w-xl space-y-4">
        @csrf
        <x-form.select name="student" label="Scout" :options="$students" :value="$selectedStudent ?? (count($students) === 1 ? array_key_first($students) : null)" placeholder="Choose a scout" required/>
        <x-form.select name="badge" label="Badge" :options="$badges" placeholder="Choose a badge" required/>
        <button class="btn-primary">Send request</button>
    </form>
</x-app-layout>
