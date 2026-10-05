<x-app-layout title="Badges">
    <x-page-header title="Badges" description="The badge catalogue and its certificate numbering.">
        <x-slot:actions>
            @can('create', \App\Models\Badge::class)
                <button type="button" class="btn-primary" x-data x-on:click="$dispatch('open-modal', 'badge-new')">Add badge</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @can('create', \App\Models\Badge::class)
        <form method="POST" action="{{ route('badges.section-codes') }}" class="card mb-4" data-testid="section-codes">
            @csrf
            <h2 class="font-semibold">Section proficiency codes</h2>
            <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Every proficiency badge of a section shares one code and one yearly number sequence, for example SCOUT-{{ now()->year }}-0001.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($sectionCodes as $section => $code)
                    <x-form.input :name="'codes['.$section.']'" :label="$section" :value="$code" class="uppercase"/>
                @endforeach
            </div>
            <div class="mt-3"><button type="submit" class="btn-primary btn-sm">Save codes</button></div>
        </form>
    @endcan

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Name or code"/>
        <x-form.select name="section" label="Section" :options="\App\Enums\ScoutSection::options()" :value="request('section')" placeholder="Any section"/>
    </x-filters>

    @if ($badges->isEmpty())
        <x-empty message="No badges yet."/>
    @else
        <x-table :headers="['Badge', 'Code', 'Section', 'Category', 'Template', 'Next number', '']">
            @foreach ($badges as $badge)
                <tr>
                    <td data-label="Badge" class="font-medium">
                        <div class="flex items-center gap-2">
                            @if ($url = photo_url($badge->image_path))<img src="{{ $url }}" alt="" class="h-8 w-8 rounded object-cover">@endif
                            {{ $badge->name }}
                        </div>
                    </td>
                    <td data-label="Code" class="font-mono text-xs">{{ $badge->code }}</td>
                    <td data-label="Section">{{ $badge->section?->value ?? '—' }}</td>
                    <td data-label="Category">
                        {{ ucfirst($badge->category) }}
                        @if ($badge->isSectionProficiency())
                            <span class="block text-xs text-gray-500">Shared {{ $badge->section->value }} sequence</span>
                        @endif
                    </td>
                    <td data-label="Template">{{ $badge->certificateTemplate?->name ?? 'Default' }}</td>
                    <td data-label="Next number" class="font-mono text-xs">{{ $numbers->peekBadgeNumber($badge) }}</td>
                    <td class="whitespace-nowrap text-right">
                        @can('update', $badge)
                            <button type="button" class="link" x-data x-on:click="$dispatch('open-modal', 'badge-{{ $badge->uuid }}')">Edit</button>
                        @endcan
                        @can('delete', $badge)
                            <x-confirm :action="route('badges.destroy', $badge)" method="DELETE" label="Delete" variant="secondary" message="Delete this badge? Badges with requests or certificates cannot be deleted." confirm="Delete"/>
                        @endcan
                    </td>
                </tr>
                @can('update', $badge)
                    <x-modal :name="'badge-'.$badge->uuid" :title="'Edit '.$badge->name" maxWidth="lg">
                        <form method="POST" action="{{ route('badges.update', $badge) }}" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            @method('PUT')
                            @include('badges.partials.fields', ['badge' => $badge])
                            <div class="flex justify-end"><button class="btn-primary">Save</button></div>
                        </form>
                    </x-modal>
                @endcan
            @endforeach
        </x-table>
        <div class="mt-4">{{ $badges->links() }}</div>
    @endif

    @can('create', \App\Models\Badge::class)
        <x-modal name="badge-new" title="Add badge" maxWidth="lg">
            <form method="POST" action="{{ route('badges.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @include('badges.partials.fields', ['badge' => new \App\Models\Badge])
                <div class="flex justify-end"><button class="btn-primary">Add badge</button></div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
