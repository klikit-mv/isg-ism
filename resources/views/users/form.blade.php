@php
    $editing = $user->exists;
    $userRoles = $editing ? $user->roles()->map->value->all() : [];
    $userPermissions = $editing ? $user->permissions()->map->value->all() : [];
@endphp
<x-app-layout :title="$editing ? 'Edit user' : 'Create user'">
    <x-page-header :title="$editing ? $user->name : 'Create user'" :description="$editing ? $user->national_id : 'A new account with roles and permissions.'">
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="btn-secondary">Back to users</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" class="card space-y-4 lg:col-span-2">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="name" label="Name" :value="$user->name" required/>
                @if ($editing)
                    <div><span class="label">National ID</span><p class="py-2 text-sm">{{ $user->national_id }}</p></div>
                @else
                    <x-form.input name="national_id" label="National ID" required class="uppercase"/>
                @endif
                <x-form.input name="email" label="Email" type="email" :value="$user->email" :required="! $editing"/>
                @if ($editing)
                    <x-form.select name="status" label="Status" :options="\App\Enums\UserStatus::options()" :value="$user->status"/>
                @else
                    <x-form.input name="pin" label="PIN" type="password" required help="At least 4 characters."/>
                @endif
                <x-form.select name="student_id" label="Linked scout (optional)" :options="$students" :value="$user->student_id" placeholder="None"/>
            </div>

            <fieldset>
                <legend class="label">Roles</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (\App\Enums\Role::cases() as $role)
                        <label class="flex items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                            <input type="checkbox" name="roles[]" value="{{ $role->value }}" @checked(in_array($role->value, old('roles', $userRoles), true)) class="mt-0.5 rounded border-gray-300 text-navy-600">
                            <span><span class="font-medium">{{ $role->label() }}</span><span class="block text-xs text-gray-500">{{ $role->description() }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="label">Extra permissions</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (\App\Enums\Permission::cases() as $permission)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" @checked(in_array($permission->value, old('permissions', $userPermissions), true)) class="rounded border-gray-300 text-navy-600">
                            {{ $permission->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button type="submit" class="btn-primary">{{ $editing ? 'Save changes' : 'Create user' }}</button>
        </form>

        @if ($editing)
            <div class="space-y-6">
                <form method="POST" action="{{ route('users.pin', $user) }}" class="card space-y-3">
                    @csrf
                    <h2 class="font-semibold">Reset PIN</h2>
                    <p class="text-sm text-gray-500">This signs the user out on every device.</p>
                    <x-form.input name="pin" label="New PIN" type="password" required/>
                    <button type="submit" class="btn-primary btn-sm">Reset PIN</button>
                </form>

                @if ($user->hasAnyRole([\App\Enums\Role::Admin, \App\Enums\Role::Leader]))
                    <form method="POST" action="{{ route('users.signature', $user) }}" enctype="multipart/form-data" class="card space-y-3">
                        @csrf
                        <h2 class="font-semibold">Signature</h2>
                        @if ($user->signature_path)<p class="text-sm text-emerald-700 dark:text-emerald-300">A signature is on file.</p>@endif
                        <x-form.file-drop name="signature" accept="image/png,image/jpeg" help="PNG or JPEG, up to 2 MB."/>
                        <button type="submit" class="btn-primary btn-sm">Upload</button>
                    </form>
                @endif

                @unless ($user->is(auth()->user()))
                    <div class="card space-y-3">
                        <h2 class="font-semibold">Delete account</h2>
                        <x-confirm :action="route('users.destroy', $user)" method="DELETE" label="Delete user" message="Delete {{ $user->name }}? Their history is kept, but they can no longer sign in." confirm="Delete"/>
                    </div>
                @endunless
            </div>
        @endif
    </div>
</x-app-layout>
