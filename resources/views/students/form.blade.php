@php $editing = $student->exists; @endphp
<x-app-layout :title="$editing ? 'Edit scout' : 'Enrol scout'">
    <x-page-header :title="$editing ? 'Edit '.$student->name : 'Enrol a scout'" :description="$editing ? 'Changes to name, National ID, email and status are copied to their account.' : 'Creates the scout and their sign-in account.'">
        <x-slot:actions>
            <a href="{{ $editing ? route('students.show', $student) : route('students.index') }}" class="btn-secondary">Cancel</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('students.update', $student) : route('students.store') }}" enctype="multipart/form-data" class="card space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        @include('students.partials.fields', ['student' => $student])

        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.select name="status" label="Status" :options="\App\Enums\StudentStatus::options()" :value="$student->status"/>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @unless ($editing)
                <x-form.input name="pin" label="PIN (optional)" type="password" help="Leave blank to generate a temporary 4-digit PIN."/>
            @endunless
            <div>
                <x-form.file-drop name="photo" label="Photo (optional)" accept="image/png,image/jpeg,image/webp" help="PNG, JPEG or WebP, up to 5 MB."/>
                @if ($editing && $student->photo_path)
                    <div class="mt-2"><x-form.checkbox name="remove_photo" label="Remove the current photo"/></div>
                @endif
            </div>
        </div>

        <button type="submit" class="btn-primary">{{ $editing ? 'Save changes' : 'Enrol scout' }}</button>
    </form>
</x-app-layout>
