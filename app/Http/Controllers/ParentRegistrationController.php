<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentRegistrationController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isStaff(), 403);

        $tab = $request->query('tab') === 'verified' ? 'verified' : 'pending';

        $parents = ($tab === 'verified' ? $this->verifiedQuery()->with('verifier')->orderByDesc('verified_at') : $this->pendingQuery()->orderBy('created_at'))
            ->with(['parentLinks.student'])
            ->paginate(Pagination::MAX)
            ->withQueryString();

        return view('parent-registrations.index', [
            'parents' => $parents,
            'tab' => $tab,
            'pendingCount' => $this->pendingQuery()->count(),
            'verifiedCount' => $this->verifiedQuery()->count(),
        ]);
    }

    public function verify(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isStaff(), 403);
        abort_unless($this->pendingQuery()->whereKey($user->id)->exists(), 404);

        $this->users->verifyParentRegistration($user, $request->user());

        return back()->with('success', "{$user->name} is verified.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isStaff(), 403);
        abort_unless($this->pendingQuery()->whereKey($user->id)->exists(), 404);

        $this->users->rejectParentRegistration($user, $request->user());

        return back()->with('success', "{$user->name}'s registration was declined.");
    }

    /**
     * Parents a leader has verified.
     */
    private function verifiedQuery()
    {
        return User::query()->withRole(Role::Parent)->whereNotNull('verified_at');
    }

    /**
     * Inactive, never-verified parents.
     */
    private function pendingQuery()
    {
        return User::query()
            ->withRole(Role::Parent)
            ->where('status', UserStatus::Inactive->value)
            ->whereNull('verified_at');
    }
}
