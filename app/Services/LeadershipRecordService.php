<?php

namespace App\Services;

use App\Models\LeadershipRecord;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeadershipRecordService
{
    public function __construct(private AuditLogService $audit, private LeaderScopeService $scope) {}

    /**
     * @param  array{q?: ?string}  $filters
     */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $query = LeadershipRecord::query()
            ->with('student', 'certificate')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('patrol_or_six', 'like', "%{$term}%")
                ->orWhere('troop_or_group', 'like', "%{$term}%")
                ->orWhereHas('student', fn ($s) => $s->where('name', 'like', "%{$term}%"))));

        return $this->scope->constrainByStudent($query, $user)
            ->orderByDesc('start_date')
            ->paginate(Pagination::MAX)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): LeadershipRecord
    {
        $record = LeadershipRecord::query()->create($this->attributes($data) + ['created_by' => $actor->id, 'updated_by' => $actor->id]);
        $this->audit->record('leadership.created', $record, ['patrol_or_six' => $record->patrol_or_six], $actor);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LeadershipRecord $record, array $data, User $actor): LeadershipRecord
    {
        $record->update($this->attributes($data) + ['updated_by' => $actor->id]);
        $this->audit->record('leadership.updated', $record, [], $actor);

        return $record;
    }

    public function delete(LeadershipRecord $record, User $actor): void
    {
        $this->audit->record('leadership.deleted', $record, ['patrol_or_six' => $record->patrol_or_six], $actor);
        $record->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'student_id' => $data['student_id'],
            'patrol_or_six' => $data['patrol_or_six'],
            'troop_or_group' => filled($data['troop_or_group'] ?? null) ? $data['troop_or_group'] : config('scout.organisation'),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
        ];
    }
}
