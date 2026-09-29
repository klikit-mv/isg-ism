<?php

namespace App\Models;

use App\Enums\BadgeRequestStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BadgeRequest extends Model
{
    use HasUuid;

    protected $fillable = [
        'request_id', 'student_id', 'student_name', 'badge_id', 'badge_name', 'status',
        'certificate_number', 'date_awarded', 'certificate_path', 'requested_by',
        'reviewed_by', 'reviewed_at', 'review_note', 'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BadgeRequestStatus::class,
            'date_awarded' => 'date',
            'reviewed_at' => 'datetime',
            'generated_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /**
     * @return HasOne<Certificate, $this>
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }
}
