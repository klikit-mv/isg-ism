<?php

namespace App\Models;

use App\Enums\ScoutSection;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    use HasUuid;

    public const CATEGORY_PROFICIENCY = 'proficiency';

    protected $fillable = [
        'badge_id', 'name', 'code', 'section', 'description', 'category',
        'image_path', 'certificate_template_id', 'number_prefix',
    ];

    protected function casts(): array
    {
        return ['section' => ScoutSection::class];
    }

    /**
     * @return BelongsTo<CertificateTemplate, $this>
     */
    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    /**
     * @return HasMany<BadgeRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(BadgeRequest::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function isSectionProficiency(): bool
    {
        return strtolower((string) $this->category) === self::CATEGORY_PROFICIENCY && $this->section !== null;
    }
}
