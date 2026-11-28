<?php

namespace App\Http\Controllers;

use App\Enums\ScoutSection;
use App\Models\Group;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('promote', Student::class);

        $from = ScoutSection::tryFrom((string) $request->query('from')) ?? ScoutSection::PreCub;
        $to = $from->next();

        $candidates = Student::query()
            ->where('section', $from->value)
            ->search($request->query('q'))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('group'), fn ($q, $group) => $q->whereHas('groups', fn ($g) => $g->where('groups.uuid', $group)))
            ->orderBy('name')
            ->get();

        return view('students.promote', [
            'from' => $from,
            'to' => $to,
            'candidates' => $candidates,
            'groups' => Group::query()->orderBy('name')->pluck('name', 'uuid')->all(),
        ]);
    }

    public function store(Request $request, StudentService $students): RedirectResponse
    {
        $this->authorize('promote', Student::class);

        $data = $request->validate([
            'from' => ['required', Rule::enum(ScoutSection::class)],
            'to' => ['required', Rule::enum(ScoutSection::class)],
            'students' => ['required', 'array', 'min:1'],
            'students.*' => ['integer'],
        ]);

        $result = $students->bulkPromote($data['students'], ScoutSection::from($data['from']), ScoutSection::from($data['to']), $request->user());

        return redirect()->route('promotion.index', ['from' => $data['from']])
            ->with('success', "{$result['promoted']} scout(s) promoted to {$data['to']}; {$result['skipped']} skipped.");
    }
}
