<?php

namespace App\Services;

use App\Enums\ParentLinkStatus;
use App\Enums\Role;
use App\Exceptions\ScoutException;
use App\Models\ParentStudentLink;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A scout may have only one pending or approved parent.
 */
class ParentLinkService
{
    public function __construct(private AuditLogService $audit) {}

    public function activeLinkFor(Student $student, ?int $exceptLinkId = null): ?ParentStudentLink
    {
        return ParentStudentLink::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [ParentLinkStatus::Pending->value, ParentLinkStatus::Approved->value])
            ->when($exceptLinkId, fn ($q) => $q->whereKeyNot($exceptLinkId))
            ->first();
    }

    public function assignedParent(Student $student): ?User
    {
        return $this->activeLinkFor($student)?->parent;
    }

    public function assertStudentHasNoOtherParent(Student $student, ?int $exceptLinkId = null): void
    {
        if ($this->activeLinkFor($student, $exceptLinkId) !== null) {
            throw new ScoutException('This scout is already added under another parent.');
        }
    }

    public function create(User $parent, Student $student, ParentLinkStatus $status = ParentLinkStatus::Approved, ?User $actor = null): ParentStudentLink
    {
        return DB::transaction(function () use ($parent, $student, $status, $actor): ParentStudentLink {
            if ($status->isOpen()) {
                $this->assertStudentHasNoOtherParent($student);
            }

            $existing = ParentStudentLink::query()->where('parent_user_id', $parent->id)->where('student_id', $student->id)->first();

            if ($existing) {
                $existing->update(['status' => $status]);
                $link = $existing;
            } else {
                $link = ParentStudentLink::query()->create([
                    'parent_user_id' => $parent->id,
                    'student_id' => $student->id,
                    'status' => $status,
                ]);
            }

            $parent->assignRole(Role::Parent);
            $this->audit->record('parent_link.created', $link, ['parent' => $parent->national_id, 'student' => $student->national_id, 'status' => $status], $actor);

            return $link;
        });
    }

    public function setStatus(ParentStudentLink $link, ParentLinkStatus $status, ?User $actor = null): ParentStudentLink
    {
        return DB::transaction(function () use ($link, $status, $actor): ParentStudentLink {
            if ($status->isOpen()) {
                $this->assertStudentHasNoOtherParent($link->student, $link->id);
            }

            $from = $link->status;
            $link->update(['status' => $status]);
            $this->audit->record('parent_link.status_changed', $link, ['from' => $from, 'to' => $status], $actor);

            return $link;
        });
    }
}
