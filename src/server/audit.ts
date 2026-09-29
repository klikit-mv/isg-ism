import { db, schema, type DbLike } from '@/db';

export interface AuditEntity {
  type: string;
  id: string | number;
}

/** Keys that must never reach the audit trail. */
const REDACTED = new Set(['pin', 'pin_confirmation', 'password', 'password_confirmation', 'pin_salt', 'legacy_pin_hash', 'legacy_pin_salt', 'current_pin', 'token']);

/** Remove secrets (recursively) from audit details. */
export function scrub(details: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(details)) {
    if (REDACTED.has(key.toLowerCase())) continue;
    out[key] = value && typeof value === 'object' && !Array.isArray(value) && !(value instanceof Date) ? scrub(value as Record<string, unknown>) : value;
  }
  return out;
}

/** "parent_link" -> "ParentLink", like the model names the old portal stored. */
const pascal = (type: string) => type.split(/[_\s-]+/).map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join('');

/**
 * Write an audit entry. Secrets (PINs, tokens, keys) are stripped from `details`.
 */
export async function recordAudit(
  action: string,
  entity: AuditEntity | null,
  details: Record<string, unknown>,
  actorUserId: number | null,
  conn: DbLike = db,
): Promise<void> {
  await conn.insert(schema.auditLogs).values({
    action,
    entityType: entity ? pascal(entity.type) : null,
    entityId: entity ? String(entity.id) : null,
    actorUserId,
    details: scrub(details),
    createdAt: new Date(),
  });
}
