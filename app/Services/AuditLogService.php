<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Keys that must never reach the audit trail.
     */
    private const REDACTED = ['pin', 'pin_confirmation', 'password', 'password_confirmation', 'pin_salt', 'legacy_pin_hash', 'legacy_pin_salt', 'current_pin', 'token'];

    /**
     * @param  array<string, mixed>  $details
     */
    public function record(string $action, ?Model $entity = null, array $details = [], ?User $actor = null): AuditLog
    {
        $actor ??= Auth::user();

        return AuditLog::query()->create([
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity ? (string) ($entity->getAttribute('uuid') ?? $entity->getKey()) : null,
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'details' => $this->scrub($details),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function scrub(array $details): array
    {
        foreach ($details as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED, true)) {
                unset($details[$key]);

                continue;
            }

            if (is_array($value)) {
                $details[$key] = $this->scrub($value);
            } elseif ($value instanceof \BackedEnum) {
                $details[$key] = $value->value;
            }
        }

        return $details;
    }
}
