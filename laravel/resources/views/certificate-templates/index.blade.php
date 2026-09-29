<x-app-layout title="Certificate templates">
    <x-page-header title="Certificate templates" description="Google Slides files with placeholders such as name, date and certno in double braces. The built-in layout is used when Slides is unavailable.">
        <x-slot:actions>
            @can('create', \App\Models\CertificateTemplate::class)
                <button type="button" class="btn-primary" x-data x-on:click="$dispatch('open-modal', 'template-new')">Add template</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($templates->isEmpty())
        <x-empty message="No templates yet. Add one for each certificate type."/>
    @else
        <x-table :headers="['Template', 'Type', 'Source', 'Activity', 'Used', 'Status', '']">
            @foreach ($templates as $template)
                <tr>
                    <td data-label="Template" class="font-medium">{{ $template->name }} <span class="block font-mono text-xs text-gray-400">{{ $template->template_id }}</span></td>
                    <td data-label="Type"><x-badge :value="$template->type"/></td>
                    <td data-label="Source">{{ $template->usesGoogleSlides() ? 'Google Slides' : 'Built-in layout' }}</td>
                    <td data-label="Activity">{{ $template->activity?->name ?? '—' }}</td>
                    <td data-label="Used">{{ $template->certificates_count }}</td>
                    <td data-label="Status"><x-badge :value="$template->active ? 'Active' : 'Inactive'" :tone="$template->active ? 'green' : 'gray'"/></td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('certificate-templates.preview', $template) }}" class="link" target="_blank" rel="noopener">Preview</a>
                        @can('update', $template)
                            <button type="button" class="link ml-2" x-data x-on:click="$dispatch('open-modal', 'template-{{ $template->uuid }}')">Edit</button>
                            <form method="POST" action="{{ route('certificate-templates.activate', $template) }}" class="inline">
                                @csrf
                                <input type="hidden" name="active" value="{{ $template->active ? 0 : 1 }}">
                                <button class="link ml-2">{{ $template->active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        @endcan
                        @can('delete', $template)
                            @if ($template->certificates_count === 0)
                                <x-confirm :action="route('certificate-templates.destroy', $template)" method="DELETE" label="Delete" variant="secondary" message="Delete this template?" confirm="Delete"/>
                            @endif
                        @endcan
                    </td>
                </tr>
                @can('update', $template)
                    <x-modal :name="'template-'.$template->uuid" :title="'Edit '.$template->name" maxWidth="lg">
                        <form method="POST" action="{{ route('certificate-templates.update', $template) }}" class="space-y-4">
                            @csrf
                            @method('PUT')
                            @include('certificate-templates.partials.fields', ['template' => $template])
                            <div class="flex justify-end"><button class="btn-primary">Save</button></div>
                        </form>
                    </x-modal>
                @endcan
            @endforeach
        </x-table>
        <div class="mt-4">{{ $templates->links() }}</div>
    @endif

    @can('create', \App\Models\CertificateTemplate::class)
        <x-modal name="template-new" title="Add template" maxWidth="lg">
            <form method="POST" action="{{ route('certificate-templates.store') }}" class="space-y-4">
                @csrf
                @include('certificate-templates.partials.fields', ['template' => new \App\Models\CertificateTemplate(['active' => true])])
                <div class="flex justify-end"><button class="btn-primary">Add template</button></div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
