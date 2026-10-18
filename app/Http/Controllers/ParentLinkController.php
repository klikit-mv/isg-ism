<?php

namespace App\Http\Controllers;

use App\Enums\ParentLinkStatus;
use App\Models\ParentStudentLink;
use App\Models\Student;
use App\Models\User;
use App\Services\ParentLinkService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParentLinkController extends Controller
{
    public function __construct(private ParentLinkService $links) {}

    public function index(Request $request): View
    {
        $links = ParentStudentLink::query()
            ->with('parent', 'student')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereHas('parent', fn ($p) => $p->where('name', 'like', "%{$term}%")->orWhere('national_id', 'like', "%{$term}%"))
                ->orWhereHas('student', fn ($s) => $s->where('name', 'like', "%{$term}%")->orWhere('national_id', 'like', "%{$term}%"))))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(Pagination::MAX)
            ->withQueryString();

        return view('parent-links.index', [
            'links' => $links,
            'parents' => User::query()->orderBy('name')->get(['id', 'name', 'national_id'])->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->national_id})"])->all(),
            'students' => Student::query()->orderBy('name')->get(['id', 'name', 'national_id'])->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->national_id})"])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'parent_user_id' => ['required', 'integer', 'exists:users,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'status' => ['nullable', Rule::enum(ParentLinkStatus::class)],
        ]);

        $this->links->create(
            User::query()->findOrFail($data['parent_user_id']),
            Student::query()->findOrFail($data['student_id']),
            ParentLinkStatus::tryFrom((string) ($data['status'] ?? '')) ?? ParentLinkStatus::Approved,
            $request->user(),
        );

        return back()->with('success', 'The parent link was saved.');
    }

    public function update(Request $request, ParentStudentLink $parentLink): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(ParentLinkStatus::class)]]);
        $this->links->setStatus($parentLink, ParentLinkStatus::from($data['status']), $request->user());

        return back()->with('success', 'The link status was changed.');
    }
}
