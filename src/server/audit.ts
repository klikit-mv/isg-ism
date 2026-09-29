import { db, schema, type DbLike } from '@/db';

export interface AuditEntity {
  type: string;
  id: string | number;
}

/**
 * Write an audit entry. Never put secrets (PINs, tokens, keys) in `details`.
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
    entityType: entity?.type ?? null,
    entityId: entity ? String(entity.id) : null,
    actorUserId,
    details,
    createdAt: new Date(),
  });
}
