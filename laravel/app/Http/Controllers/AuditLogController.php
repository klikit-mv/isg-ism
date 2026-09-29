<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('actor')
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', 'like', $action.'%'))
            ->when($request->query('entity'), fn ($q, $entity) => $q->where('entity_type', $entity))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(Pagination::MAX)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'entities' => AuditLog::query()->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type', 'entity_type')->all(),
        ]);
    }
}
