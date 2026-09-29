<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasUuid;

    protected $fillable = [
        'cert_id', 'type', 'student_id', 'student_name', 'title', 'cert_number', 'id_card_no',
        'date_awarded', 'path', 'status', 'badge_id', 'badge_name', 'template_id', 'activity_id',
        'badge_request_id', 'created_by', 'generated_by', 'generated_at', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'status' => CertificateStatus::class,
            'date_awarded' => 'date',
            'generated_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Badge, $this>
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    /**
     * @return BelongsTo<CertificateTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class)->withTrashed();
    }

    /**
     * @return BelongsTo<BadgeRequest, $this>
     */
    public function badgeRequest(): BelongsTo
    {
        return $this->belongsTo(BadgeRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    public function displayTitle(): string
    {
        return match ($this->type) {
            CertificateType::Badge => (string) ($this->badge_name ?? 'Badge'),
            CertificateType::Leadership => 'Leadership certificate',
            default => (string) ($this->title ?? 'Certificate'),
        };
    }
}
