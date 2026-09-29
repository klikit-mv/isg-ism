import { and, count, desc, eq, like, or } from 'drizzle-orm';
import { db, schema } from '@/db';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import { config } from '@/lib/config';
import { recordAudit } from './audit';
import { studentIdScope } from './scope';
import type { AuthUser } from './users';

export type LeadershipRow = typeof schema.leadershipRecords.$inferSelect;
const L = schema.leadershipRecords;

export const findLeadershipByUuid = async (uuid: string): Promise<LeadershipRow | null> => (await db.select().from(L).where(eq(L.uuid, uuid)).limit(1))[0] ?? null;

export async function listLeadership(user: AuthUser, f: { q?: string; studentId?: number; page: number }) {
  const S = schema.students;
  const term = f.q?.trim();
  const where = and(
    f.studentId ? eq(L.studentId, f.studentId) : undefined,
    term ? or(like(L.patrolOrSix, `%${term}%`), like(L.troopOrGroup, `%${term}%`), like(S.name, `%${term}%`)) : undefined,
    await studentIdScope(user, L.studentId),
  );
  const [{ n }] = await db.select({ n: count() }).from(L).innerJoin(S, eq(S.id, L.studentId)).where(where);
  const rows = await db.select({ record: L, student: S.name, certNumber: schema.certificates.certNumber, certUuid: schema.certificates.uuid }).from(L)
    .innerJoin(S, eq(S.id, L.studentId)).leftJoin(schema.certificates, eq(schema.certificates.id, L.certificateId))
    .where(where).orderBy(desc(L.startDate)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), f.page);
}

export interface LeadershipInput { student_id: number; patrol_or_six: string; troop_or_group?: string | null; start_date: string; end_date?: string | null }

const attributes = (d: LeadershipInput) => ({
  studentId: d.student_id, patrolOrSix: d.patrol_or_six, troopOrGroup: d.troop_or_group?.trim() ? d.troop_or_group : config.organisation, startDate: d.start_date, endDate: d.end_date || null,
});

export async function createLeadership(data: LeadershipInput, actor: AuthUser): Promise<LeadershipRow> {
  const now = new Date();
  const [ins] = await db.insert(L).values({ ...attributes(data), createdBy: actor.id, updatedBy: actor.id, createdAt: now, updatedAt: now }).$returningId();
  const [row] = await db.select().from(L).where(eq(L.id, ins.id));
  await recordAudit('leadership.created', { type: 'leadership_record', id: row.uuid }, { patrol_or_six: row.patrolOrSix }, actor.id);
  return row;
}

export async function updateLeadership(row: LeadershipRow, data: LeadershipInput, actor: AuthUser): Promise<void> {
  await db.update(L).set({ ...attributes(data), updatedBy: actor.id, updatedAt: new Date() }).where(eq(L.id, row.id));
  await recordAudit('leadership.updated', { type: 'leadership_record', id: row.uuid }, {}, actor.id);
}

export async function deleteLeadership(row: LeadershipRow, actor: AuthUser): Promise<void> {
  await recordAudit('leadership.deleted', { type: 'leadership_record', id: row.uuid }, { patrol_or_six: row.patrolOrSix }, actor.id);
  await db.delete(L).where(eq(L.id, row.id));
}
