<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Student;
use App\Models\User;
use App\Services\UserService;
use App\Support\Pagination;
use App\Support\Sort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->with('roleRows', 'permissionRows')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('national_id', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($request->query('role'), fn ($q, $role) => $q->whereHas('roleRows', fn ($r) => $r->where('role', $role)))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->tap(fn ($q) => Sort::apply($q, $request, ['name' => 'name', 'national_id' => 'national_id', 'email' => 'email', 'status' => 'status'], 'name'))
            ->paginate(Pagination::MAX)
            ->withQueryString();

        return view('users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User, 'students' => $this->studentOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'national_id' => ['required', 'string', 'max:64', Rule::unique('users', 'national_id')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'pin' => ['required', 'string', 'min:4', 'max:32'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(Role::class)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
            'student_id' => ['nullable', 'integer', 'exists:students,id', Rule::unique('users', 'student_id')],
        ]);

        $user = $this->users->create($data, $request->user());

        return redirect()->route('users.edit', $user)->with('success', "{$user->name} was created.");
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user->load('roleRows', 'permissionRows'), 'students' => $this->studentOptions()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'roles' => ['array'],
            'roles.*' => [Rule::enum(Role::class)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
            'student_id' => ['nullable', 'integer', 'exists:students,id', Rule::unique('users', 'student_id')->ignore($user->id)],
        ]);

        $this->users->update($user, $data, $request->user());

        return redirect()->route('users.edit', $user)->with('success', 'The user was saved.');
    }

    public function pin(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'string', 'min:4', 'max:32']]);
        $this->users->resetPin($user, $data['pin'], $request->user());

        return redirect()->route('users.edit', $user)->with('success', 'The PIN was reset and the user was signed out everywhere.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->users->delete($user, $request->user());

        return redirect()->route('users.index')->with('success', 'The user was deleted.');
    }

    /**
     * @return array<int, string>
     */
    private function studentOptions(): array
    {
        return Student::query()->orderBy('name')->get(['id', 'name', 'national_id'])
            ->mapWithKeys(fn (Student $s) => [$s->id => "{$s->name} ({$s->national_id})"])
            ->all();
    }
}
