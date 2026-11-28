<x-app-layout title="Check certificates">
    <x-page-header title="Check the scouts" :description="count($rows).' file(s) from '.$issuer.'. Confirm which scout each one belongs to, then save.'">
        <x-slot:actions><a href="{{ route('certificates.external') }}" class="btn-secondary">Start again</a></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('certificates.external.store') }}" class="space-y-4" data-testid="external-review">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="issuer" value="{{ $issuer }}">
        <input type="hidden" name="date_awarded" value="{{ $date }}">

        <div class="space-y-3">
            @foreach ($rows as $i => $row)
                <div class="card !p-4" x-data="{ skip: false }">
                    <input type="hidden" name="rows[{{ $i }}][key]" value="{{ $row['key'] }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="min-w-0 truncate text-sm font-medium">{{ $row['file'] }}</p>
                        @if ($row['student_id'])
                            <x-badge tone="green">Matched</x-badge>
                        @else
                            <x-badge tone="amber">Choose a scout</x-badge>
                        @endif
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2" x-bind:class="skip && 'opacity-40'">
                        <x-form.select :name="'rows['.$i.'][student_id]'" label="Scout" :options="$students" :value="$row['student_id']" placeholder="Choose a scout"/>
                        <x-form.input :name="'rows['.$i.'][title]'" label="Certificate title" :value="$row['title']"/>
                    </div>
                    <label class="mt-3 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" name="rows[{{ $i }}][skip]" value="1" x-model="skip" class="rounded border-gray-300 text-navy-600">
                        Skip this file
                    </label>
                </div>
            @endforeach
        </div>

        <button class="btn-primary">Save certificates</button>
    </form>
</x-app-layout>
