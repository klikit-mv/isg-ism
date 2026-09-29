import { eq } from 'drizzle-orm';
import { db, schema, type DbLike } from '@/db';
import { sectionNumberPrefix, ScoutSection, type ScoutSectionValue } from '@/lib/enums';
import { todayLocal } from '@/lib/dates';

/** Yearly sequences (organisation timezone) behind row-locked counters. */
export const currentYear = (): number => Number(todayLocal().slice(0, 4));

export const formatNumber = (prefix: string, year: number, n: number): string => `${prefix}-${year}-${String(n).padStart(4, '0')}`;

type BadgeLike = { id: number; badgeId: string; code: string; section: string | null; category: string; numberPrefix: string | null };

/** Proficiency badges of one section share a yearly sequence; other badges count on their own. */
export const isSectionProficiency = (b: Pick<BadgeLike, 'category' | 'section'>) => b.category.toLowerCase() === 'proficiency' && ScoutSection.is(b.section);

export function badgeSequence(badge: BadgeLike, year = currentYear()): { counter: string; prefix: string } {
  if (isSectionProficiency(badge)) {
    return { counter: `badge:proficiency:${badge.section}:${year}`, prefix: sectionNumberPrefix(badge.section as ScoutSectionValue) };
  }
  return { counter: `badge:${badge.badgeId}:${year}`, prefix: (badge.numberPrefix || badge.code).toUpperCase() };
}

const C = schema.certificateCounters;

async function lockedCounter(conn: DbLike, counterId: string, year: number, badgeId: number | null) {
  const now = new Date();
  await conn.insert(C).ignore().values({ counterId, year, badgeId, lastNumber: 0, createdAt: now, updatedAt: now });
  const [row] = await conn.select().from(C).where(eq(C.counterId, counterId)).for('update');
  return row;
}

/** Take the next number, skipping any already used on a certificate. Call inside a transaction. */
export async function nextSequence(conn: DbLike, counterId: string, year: number, prefix: string, badgeId: number | null = null): Promise<string> {
  const counter = await lockedCounter(conn, counterId, year, badgeId);
  let next = counter.lastNumber + 1;
  for (;;) {
    const [used] = await conn.select({ id: schema.certificates.id }).from(schema.certificates).where(eq(schema.certificates.certNumber, formatNumber(prefix, year, next))).limit(1);
    if (!used) break;
    next++;
  }
  await conn.update(C).set({ lastNumber: next, updatedAt: new Date() }).where(eq(C.id, counter.id));
  return formatNumber(prefix, year, next);
}

export const nextBadgeNumber = (conn: DbLike, badge: BadgeLike) => {
  const year = currentYear();
  const s = badgeSequence(badge, year);
  return nextSequence(conn, s.counter, year, s.prefix, badge.id);
};
export const nextGeneralNumber = (conn: DbLike) => nextSequence(conn, `general:${currentYear()}`, currentYear(), 'CERT');
export const nextLeadershipNumber = (conn: DbLike) => nextSequence(conn, `leadership:${currentYear()}`, currentYear(), 'LEAD');

export async function peekSequence(counterId: string): Promise<number> {
  const [row] = await db.select({ n: C.lastNumber }).from(C).where(eq(C.counterId, counterId)).limit(1);
  return (row?.n ?? 0) + 1;
}

/** What the next number of a badge would be. */
export async function peekBadgeNumber(badge: BadgeLike): Promise<string> {
  const year = currentYear();
  const s = badgeSequence(badge, year);
  return formatNumber(s.prefix, year, await peekSequence(s.counter));
}

/** Let an admin choose the next number for a sequence. */
export async function setNextSequence(counterId: string, next: number, badgeId: number | null = null): Promise<void> {
  const year = Number(counterId.slice(counterId.lastIndexOf(':') + 1));
  await db.transaction(async (tx) => {
    const counter = await lockedCounter(tx, counterId, year, badgeId);
    await tx.update(C).set({ lastNumber: Math.max(0, next - 1), updatedAt: new Date() }).where(eq(C.id, counter.id));
  });
}
