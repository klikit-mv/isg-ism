<x-app-layout title="Parent links">
    <x-page-header title="Parent links" description="Which parent can see which scout. A scout can have only one pending or approved parent.">
        <x-slot:actions>
            <button type="button" class="btn-primary" x-data x-on:click="$dispatch('open-modal', 'create-link')">Link parent</button>
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Parent or scout"/>
        <x-form.select name="status" label="Status" :options="\App\Enums\ParentLinkStatus::options()" :value="request('status')" placeholder="Any status"/>
    </x-filters>

    @if ($links->isEmpty())
        <x-empty message="No parent links yet."/>
    @else
        <x-table :headers="['Parent', 'Scout', 'Status', 'Change status']">
            @foreach ($links as $link)
                <tr>
                    <td data-label="Parent">{{ $link->parent?->name }} <span class="text-xs text-gray-400">{{ $link->parent?->national_id }}</span></td>
                    <td data-label="Scout">{{ $link->student?->name }} <span class="text-xs text-gray-400">{{ $link->student?->national_id }}</span></td>
                    <td data-label="Status"><x-badge :value="$link->status"/></td>
                    <td data-label="Change status">
                        <form method="POST" action="{{ route('parent-links.update', $link) }}" class="flex items-center justify-end gap-2 md:justify-start">
                            @csrf
                            <select name="status" class="input !w-auto !py-1 text-xs">
                                @foreach (\App\Enums\ParentLinkStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($link->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <button class="btn-secondary btn-sm">Save</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $links->links() }}</div>
    @endif

    <x-modal name="create-link" title="Link a parent to a scout" maxWidth="lg">
        <form method="POST" action="{{ route('parent-links.store') }}" class="space-y-4">
            @csrf
            <x-form.select name="parent_user_id" label="Parent account" :options="$parents" placeholder="Choose a user" required/>
            <x-form.select name="student_id" label="Scout" :options="$students" placeholder="Choose a scout" required/>
            <x-form.select name="status" label="Status" :options="\App\Enums\ParentLinkStatus::options()" value="approved"/>
            <p class="text-xs text-gray-500">The parent role is added to the account if missing.</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'create-link')">Cancel</button>
                <button class="btn-primary">Save link</button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
