<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only: rows are never updated or deleted.
 */
class AuditLog extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = ['entity_type', 'entity_id', 'action', 'actor_user_id', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log rows are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit log rows are append-only.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
