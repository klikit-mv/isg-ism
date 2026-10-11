<x-app-layout title="Users">
    <x-page-header title="Users" description="Accounts, roles and permissions.">
        <x-slot:actions>
            <a href="{{ route('users.create') }}" class="btn-primary">Create user</a>
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Name, National ID or email"/>
        <x-form.select name="role" label="Role" :options="\App\Enums\Role::options()" :value="request('role')" placeholder="Any role"/>
        <x-form.select name="status" label="Status" :options="\App\Enums\UserStatus::options()" :value="request('status')" placeholder="Any status"/>
    </x-filters>

    @if ($users->isEmpty())
        <x-empty message="No users match these filters."/>
    @else
        <x-table :headers="[['label' => 'Name', 'sort' => 'name'], ['label' => 'National ID', 'sort' => 'national_id'], ['label' => 'Email', 'sort' => 'email'], 'Roles', ['label' => 'Status', 'sort' => 'status'], '']">
            @foreach ($users as $user)
                <tr>
                    <td data-label="Name" class="font-medium">{{ $user->name }}</td>
                    <td data-label="National ID">{{ $user->national_id }}</td>
                    <td data-label="Email">{{ $user->email ?: '—' }}</td>
                    <td data-label="Roles"><div class="flex flex-wrap justify-end gap-1 md:justify-start">@foreach ($user->roles() as $role)<x-badge :value="$role"/>@endforeach</div></td>
                    <td data-label="Status"><x-badge :value="$user->status"/></td>
                    <td class="text-right"><a href="{{ route('users.edit', $user) }}" class="link">Edit</a></td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $users->links() }}</div>
    @endif
</x-app-layout>
