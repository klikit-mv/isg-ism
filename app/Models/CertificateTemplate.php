<?php

namespace App\Models;

use App\Enums\CertificateType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    use HasUuid;

    protected $fillable = ['template_id', 'name', 'type', 'google_slide_id', 'activity_id', 'template_content', 'active'];

    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }

    /**
     * A real Google Slides id (not a local HTML layout id).
     */
    public function usesGoogleSlides(): bool
    {
        return filled($this->google_slide_id) && ! str_starts_with((string) $this->google_slide_id, 'local-');
    }
}
