<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\BadgeRequestStatus;
use App\Enums\CertificateType;
use App\Exceptions\BadgeRequestNotApproved;
use App\Exceptions\ScoutException;
use App\Exceptions\StudentNotAccessible;
use App\Models\Activity;
use App\Models\AttendanceRecord;
use App\Models\Badge;
use App\Models\BadgeRequest;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Student;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Certificate workflows: badge requests, general and activity certificates, lookups.
 */
class CertificateService
{
    public function __construct(
        private CertificateGenerationService $generator,
        private LeaderScopeService $scope,
        private ActivityRosterService $roster,
        private AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Certificate>
     */
    public function certificateQuery(User $user, array $filters = []): Builder
    {
        $query = Certificate::query()
            ->with('student')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('cert_number', 'like', "%{$term}%")
                ->orWhere('student_name', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")))
            ->when($filters['student'] ?? null, fn ($q, $uuid) => $q->whereHas('student', fn ($s) => $s->where('uuid', $uuid)))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['badge'] ?? null, fn ($q, $uuid) => $q->whereHas('badge', fn ($b) => $b->where('uuid', $uuid)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('date_awarded', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('date_awarded', '<=', $to));

        $this->scope->constrainByStudent($query, $user);

        return ($filters['sort'] ?? 'newest') === 'oldest'
            ? $query->orderBy('date_awarded')->orderBy('id')
            : $query->orderByDesc('date_awarded')->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateCertificates(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->certificateQuery($user, $filters)->paginate(Pagination::MAX)->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    public function dashboardStats(User $user): array
    {
        $certificates = $this->scope->constrainByStudent(Certificate::query(), $user);
        $requests = $this->scope->constrainByStudent(BadgeRequest::query(), $user);
        $year = (int) scout_now()->format('Y');

        return [
            'total' => (clone $certificates)->count(),
            'this_year' => (clone $certificates)->whereYear('date_awarded', $year)->count(),
            'badge' => (clone $certificates)->where('type', CertificateType::Badge->value)->count(),
            'general' => (clone $certificates)->where('type', CertificateType::General->value)->count(),
            'leadership' => (clone $certificates)->where('type', CertificateType::Leadership->value)->count(),
            'requests_pending' => (clone $requests)->where('status', BadgeRequestStatus::Requested->value)->count(),
            'requests_approved' => (clone $requests)->where('status', BadgeRequestStatus::Approved->value)->count(),
            'requests_rejected' => (clone $requests)->where('status', BadgeRequestStatus::Rejected->value)->count(),
            'generated_today' => (clone $certificates)->where('generated_at', '>=', scout_now()->startOfDay()->utc())->count(),
        ];
    }

    public function findByNumber(string $certNumber): ?Certificate
    {
        $certNumber = strtoupper(trim($certNumber));

        return $certNumber === '' ? null : Certificate::query()->with('student', 'verifier')->where('cert_number', $certNumber)->first();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function accessibleRequests(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = BadgeRequest::query()
            ->with('student', 'badge')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('student_name', 'like', "%{$term}%")
                ->orWhere('badge_name', 'like', "%{$term}%")
                ->orWhere('request_id', 'like', "%{$term}%")));

        return $this->scope->constrainByStudent($query, $user)
            ->latest()
            ->paginate(Pagination::MAX)
            ->withQueryString();
    }

    public function requestBadge(Student $student, Badge $badge, User $actor): BadgeRequest
    {
        if (! $this->scope->canAccessStudent($actor, $student)) {
            throw new StudentNotAccessible('You cannot request a badge for this scout.');
        }

        return DB::transaction(function () use ($student, $badge, $actor): BadgeRequest {
            $open = BadgeRequest::query()
                ->where('student_id', $student->id)
                ->where('badge_id', $badge->id)
                ->whereIn('status', [BadgeRequestStatus::Requested->value, BadgeRequestStatus::Approved->value, BadgeRequestStatus::Generated->value])
                ->lockForUpdate()
                ->exists();

            if ($open) {
                throw new ScoutException("{$student->name} already has a request for the {$badge->name} badge.");
            }

            $request = BadgeRequest::query()->create([
                'request_id' => 'BR-'.strtoupper(Str::random(6)),
                'student_id' => $student->id,
                'student_name' => $student->name,
                'badge_id' => $badge->id,
                'badge_name' => $badge->name,
                'status' => BadgeRequestStatus::Requested,
                'requested_by' => $actor->id,
            ]);

            $this->audit->record('badge_request.created', $request, ['badge' => $badge->code], $actor);

            return $request;
        });
    }

    public function approve(BadgeRequest $request, User $actor, ?string $note = null): void
    {
        $this->decide($request, $actor, BadgeRequestStatus::Approved, $note);
    }

    public function reject(BadgeRequest $request, User $actor, ?string $note = null): void
    {
        $this->decide($request, $actor, BadgeRequestStatus::Rejected, $note);
    }

    public function generateApproved(BadgeRequest $request, User $actor, string $date, ?CertificateTemplate $template = null): Certificate
    {
        return DB::transaction(function () use ($request, $actor, $date, $template): Certificate {
            $locked = BadgeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BadgeRequestStatus::Approved) {
                throw new BadgeRequestNotApproved('Only approved badge requests can be generated.');
            }

            $certificate = $this->generator->generateBadgeCertificate($locked->student, $locked->badge, $date, $template, $actor, $locked->id);

            $locked->update([
                'status' => BadgeRequestStatus::Generated,
                'certificate_number' => $certificate->cert_number,
                'date_awarded' => $certificate->date_awarded,
                'certificate_path' => $certificate->path,
                'generated_by' => $actor->id,
                'generated_at' => now(),
            ]);

            $this->audit->record('badge_request.generated', $locked, ['cert_number' => $certificate->cert_number], $actor);

            return $certificate;
        });
    }

    /**
     * Each scout is its own transaction; failures never roll back successes.
     *
     * @param  list<int>  $studentIds
     * @return array{created: list<Certificate>, failed: array<string, string>}
     */
    public function bulkCreateGeneral(array $studentIds, string $title, string $date, CertificateTemplate $template, User $actor): array
    {
        $created = [];
        $failed = [];

        foreach (Student::query()->whereIn('id', $studentIds)->orderBy('name')->get() as $student) {
            try {
                if (! $this->scope->canAccessStudent($actor, $student)) {
                    throw new StudentNotAccessible('Not one of your scouts.');
                }

                $created[] = $this->generator->generateGeneralCertificate($student, $title, $date, $template, $actor);
            } catch (Throwable $e) {
                $failed[$student->name] = $e->getMessage();
            }
        }

        return ['created' => $created, 'failed' => $failed];
    }

    /**
     * Issue general certificates to Present/Late scouts who do not have one for this activity yet.
     *
     * @return array{issued: int, failed: array<string, string>}
     */
    public function issueForActivityAttendance(Activity $activity, User $actor): array
    {
        if ($activity->certificate_template_id === null) {
            return ['issued' => 0, 'failed' => []];
        }

        $issued = 0;
        $failed = [];

        foreach ($this->missingStudents($activity) as $student) {
            try {
                $this->generator->generateGeneralCertificate($student, $activity->name, $activity->date, null, $actor, $activity);
                $issued++;
            } catch (Throwable $e) {
                $failed[$student->name] = $e->getMessage();
            }
        }

        return ['issued' => $issued, 'failed' => $failed];
    }

    /**
     * @return Collection<int, array{activity: Activity, present: int, issued: int, missing: int}>
     */
    public function activityCertificateSummaries(User $user): Collection
    {
        $activities = $this->scope->constrainActivities(Activity::query()->whereNotNull('certificate_template_id')->with('certificateTemplate'), $user)
            ->orderByDesc('date')
            ->limit(50)
            ->get();

        return $activities->map(function (Activity $activity) {
            $present = AttendanceRecord::query()->where('activity_id', $activity->id)
                ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])->count();
            $issued = Certificate::query()->where('activity_id', $activity->id)->where('type', CertificateType::General->value)->count();

            return ['activity' => $activity, 'present' => $present, 'issued' => $issued, 'missing' => $this->missingStudents($activity)->count()];
        });
    }

    /**
     * @return Collection<int, Student>
     */
    private function missingStudents(Activity $activity): Collection
    {
        $attendedIds = AttendanceRecord::query()->where('activity_id', $activity->id)
            ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->pluck('student_id');
        $haveIds = Certificate::query()->where('activity_id', $activity->id)->where('type', CertificateType::General->value)->pluck('student_id');

        return Student::query()->whereIn('id', $attendedIds->diff($haveIds)->values()->all() ?: [0])->orderBy('name')->get();
    }

    /**
     * Request one badge for many scouts and, when asked, approve each request and generate its certificate. Every scout
     * is handled on its own, so one failure never undoes the others.
     *
     * @param  iterable<Student>  $students
     * @return list<array{student: string, status: string, certificate: ?string, message: string}>
     */
    public function bulkRequestBadge(Badge $badge, iterable $students, User $actor, bool $approveAndGenerate, ?string $date = null, ?CertificateTemplate $template = null): array
    {
        $rows = [];

        foreach ($students as $student) {
            $row = ['student' => $student->name, 'status' => 'failed', 'certificate' => null, 'message' => ''];

            try {
                if ($badge->section !== null && $student->section !== $badge->section) {
                    throw new ScoutException("The {$badge->name} badge is for {$badge->section->value} scouts.");
                }

                $request = $this->requestBadge($student, $badge, $actor);
                $row['status'] = 'requested';
                $row['message'] = 'Request created.';

                if ($approveAndGenerate) {
                    $this->approve($request, $actor);
                    $row['status'] = 'approved';
                    $row['message'] = 'Approved.';

                    $certificate = $this->generateApproved($request->fresh(), $actor, (string) $date, $template);
                    $row['status'] = 'generated';
                    $row['certificate'] = $certificate->cert_number;
                    $row['message'] = 'Approved and certificate generated.';
                }
            } catch (ScoutException $e) {
                $row['message'] = $e->getMessage();
            } catch (Throwable $e) {
                $row['message'] = $row['status'] === 'approved' ? 'Approved, but the certificate could not be generated: '.$e->getMessage() : 'Could not be processed: '.$e->getMessage();
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Approve several requests; ones that are not waiting for a decision (or that the user may not decide) are skipped.
     *
     * @param  iterable<BadgeRequest>  $requests
     * @return array{approved: int, skipped: int}
     */
    public function approveMany(iterable $requests, User $actor, ?string $note = null): array
    {
        $approved = 0;
        $skipped = 0;

        foreach ($requests as $request) {
            if (! $actor->can('decide', $request)) {
                $skipped++;

                continue;
            }

            try {
                $this->approve($request, $actor, $note);
                $approved++;
            } catch (ScoutException) {
                $skipped++;
            }
        }

        return ['approved' => $approved, 'skipped' => $skipped];
    }

    private function decide(BadgeRequest $request, User $actor, BadgeRequestStatus $status, ?string $note): void
    {
        DB::transaction(function () use ($request, $actor, $status, $note): void {
            $locked = BadgeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BadgeRequestStatus::Requested) {
                throw new ScoutException('Only requested badges can be approved or rejected.');
            }

            $locked->update([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $this->audit->record('badge_request.'.$status->value, $locked, ['note' => $note], $actor);
        });
    }
}
