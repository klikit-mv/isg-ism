<?php

namespace App\Services;

use App\Exceptions\ScoutException;
use App\Models\Badge;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class BadgeService
{
    public function __construct(
        private AuditLogService $audit,
        private GoogleDrivePhotoService $photos,
        private CertificateNumberService $numbers,
    ) {}

    /**
     * @param  array{q?: ?string, section?: ?string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Badge::query()
            ->with('certificateTemplate')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->when($filters['section'] ?? null, fn ($q, $section) => $q->where('section', $section))
            ->orderBy('name')
            ->paginate(Pagination::MAX)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?UploadedFile $image = null): Badge
    {
        $badge = Badge::query()->create($this->attributes($data) + ['badge_id' => 'B'.strtoupper(Str::random(5))]);

        if ($image) {
            $badge->update(['image_path' => $this->photos->store($image, 'badges')]);
        }

        $this->applyNextNumber($badge, $data);
        $this->audit->record('badge.created', $badge, ['name' => $badge->name, 'code' => $badge->code], $actor);

        return $badge;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Badge $badge, array $data, User $actor, ?UploadedFile $image = null): Badge
    {
        $badge->update($this->attributes($data));

        if ($image) {
            $old = $badge->image_path;
            $badge->update(['image_path' => $this->photos->store($image, 'badges')]);
            $this->photos->delete($old);
        }

        $this->applyNextNumber($badge, $data);
        $this->audit->record('badge.updated', $badge, ['name' => $badge->name], $actor);

        return $badge;
    }

    public function delete(Badge $badge, User $actor): void
    {
        if ($badge->certificates()->exists() || $badge->requests()->exists()) {
            throw new ScoutException('This badge has requests or certificates and cannot be deleted.');
        }

        $this->audit->record('badge.deleted', $badge, ['name' => $badge->name], $actor);
        $this->photos->delete($badge->image_path);
        $badge->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'code' => strtoupper(trim((string) $data['code'])),
            'section' => $data['section'] ?? null,
            'description' => $data['description'] ?? null,
            'category' => filled($data['category'] ?? null) ? strtolower(trim((string) $data['category'])) : Badge::CATEGORY_PROFICIENCY,
            'certificate_template_id' => $data['certificate_template_id'] ?? null,
            'number_prefix' => filled($data['number_prefix'] ?? null) ? strtoupper(trim((string) $data['number_prefix'])) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyNextNumber(Badge $badge, array $data): void
    {
        if (! filled($data['next_number'] ?? null)) {
            return;
        }

        $sequence = $this->numbers->badgeSequence($badge->fresh());
        $this->numbers->setNextSequence($sequence['counter'], (int) $data['next_number'], $badge->id);
    }
}
