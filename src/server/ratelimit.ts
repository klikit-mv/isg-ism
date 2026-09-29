import { eq } from 'drizzle-orm';
import { db, schema } from '@/db';

/**
 * Small database-backed limiter (shared hosting has no Redis).
 * A window starts at the first hit and ends `decaySeconds` later.
 */
export async function hit(key: string, decaySeconds = 60): Promise<void> {
  const now = new Date();
  const [row] = await db.select().from(schema.rateLimits).where(eq(schema.rateLimits.key, key)).limit(1);
  if (!row || row.resetsAt <= now) {
    await db.insert(schema.rateLimits)
      .values({ key, hits: 1, resetsAt: new Date(now.getTime() + decaySeconds * 1000) })
      .onDuplicateKeyUpdate({ set: { hits: 1, resetsAt: new Date(now.getTime() + decaySeconds * 1000) } });
    return;
  }
  await db.update(schema.rateLimits).set({ hits: row.hits + 1 }).where(eq(schema.rateLimits.key, key));
}

export async function tooManyAttempts(key: string, max: number): Promise<boolean> {
  const [row] = await db.select().from(schema.rateLimits).where(eq(schema.rateLimits.key, key)).limit(1);
  return !!row && row.resetsAt > new Date() && row.hits >= max;
}

export async function availableIn(key: string): Promise<number> {
  const [row] = await db.select().from(schema.rateLimits).where(eq(schema.rateLimits.key, key)).limit(1);
  return row ? Math.max(0, Math.ceil((row.resetsAt.getTime() - Date.now()) / 1000)) : 0;
}

export async function clear(key: string): Promise<void> {
  await db.delete(schema.rateLimits).where(eq(schema.rateLimits.key, key));
}
