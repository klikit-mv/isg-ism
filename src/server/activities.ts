import { and, asc, count, desc, eq, gte, inArray, isNotNull, isNull, like, lte, ne } from 'drizzle-orm';
import { db, schema, type DbLike } from '@/db';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import * as money from '@/lib/money';
import { recordAudit } from './audit';
import { NotAccessibleError } from './errors';
import { notify, sendMany } from './notifications';
import { activityScope, canManageAttendance, leaderStudentIds } from './scope';
import { defaultClassFee } from './settings';
import { loadUsers, type AuthUser } from './users';

export type ActivityRow = typeof schema.activities.$inferSelect;

export const findActivityByUuid = async (uuid: string): Promise<ActivityRow | null> => {
  const [row] = await db.select().from(schema.activities).where(and(eq(schema.activities.uuid, uuid), isNull(schema.activities.deletedAt))).limit(1);
  return row ?? null;
};

/**
 * An activity roster is the de-duplicated union of all scouts (when targeted),
 * selected sections and selected groups: active scouts only.
 */
export async function resolveStudentIds(activity: Pick<ActivityRow, 'id' | 'allStudents'>, conn: DbLike = db): Promise<number[]> {
  const S = schema.students;
  const active = and(eq(S.status, 'active'), isNull(S.deletedAt));
  const ids = new Set<number>();

  if (activity.allStudents) (await conn.select({ id: S.id }).from(S).where(active)).forEach((r) => ids.add(r.id));

  const sections = (await conn.select({ s: schema.activitySections.section }).from(schema.activitySections).where(eq(schema.activitySections.activityId, activity.id))).map((r) => r.s);
  if (sections.length) (await conn.select({ id: S.id }).from(S).where(and(active, inArray(S.section, sections)))).forEach((r) => ids.add(r.id));

  const groupIds = (await conn.select({ id: schema.activityGroups.groupId }).from(schema.activityGroups)
    .innerJoin(schema.groups, eq(schema.groups.id, schema.activityGroups.groupId))
    .where(and(isNull(schema.groups.deletedAt), eq(schema.activityGroups.activityId, activity.id)))).map((r) => r.id);
  if (groupIds.length) {
    const members = (await conn.select({ id: schema.groupMembers.studentId }).from(schema.groupMembers).where(inArray(schema.groupMembers.groupId, groupIds))).map((r) => r.id);
    if (members.length) (await conn.select({ id: S.id }).from(S).where(and(active, inArray(S.id, members)))).forEach((r) => ids.add(r.id));
  }
  return [...ids];
}

export async function resolveRoster(activity: Pick<ActivityRow, 'id' | 'allStudents'>) {
  const ids = await resolveStudentIds(activity);
  return ids.length ? db.select().from(schema.students).where(inArray(schema.students.id, ids)).orderBy(asc(schema.students.name)) : [];
}

/** Roster scouts this user may mark (leaders: only their own scouts). */
export async function markableStudentIds(activity: Pick<ActivityRow, 'id' | 'allStudents'>, user: AuthUser): Promise<number[]> {
  const roster = await resolveStudentIds(activity);
  const scoped = await leaderStudentIds(user);
  return scoped === null ? roster : roster.filter((id) => scoped.includes(id));
}

export async function verifyCanManageAttendance(activity: Pick<ActivityRow, 'id'>, user: AuthUser): Promise<void> {
  if (!(await canManageAttendance(user, activity.id))) throw new NotAccessibleError('You cannot manage attendance for this activity.');
}

export interface ActivityInput {
  name: string;
  date: string;
  details?: string | null;
  all_students: boolean;
  sections?: string[];
  groups?: number[];
  charge_fee: boolean;
  fee_amount?: string | null;
  certificate_template_id?: number | null;
}

async function attributes(data: ActivityInput) {
  return {
    name: data.name, date: data.date, details: data.details ?? null, allStudents: data.all_students, chargeFee: data.charge_fee,
    feeAmount: data.charge_fee ? money.normalize(data.fee_amount ? data.fee_amount : await defaultClassFee()) : null,
    certificateTemplateId: data.certificate_template_id ?? null,
  };
}

async function syncTargets(activityId: number, data: ActivityInput, conn: DbLike) {
  await conn.delete(schema.activitySections).where(eq(schema.activitySections.activityId, activityId));
  for (const section of new Set(data.sections ?? [])) await conn.insert(schema.activitySections).values({ activityId, section });
  await conn.delete(schema.activityGroups).where(eq(schema.activityGroups.activityId, activityId));
  for (const groupId of new Set(data.groups ?? [])) await conn.insert(schema.activityGroups).values({ activityId, groupId });

  // A template belongs to at most one activity.
  const T = schema.certificateTemplates;
  const templateId = data.certificate_template_id ?? null;
  await conn.update(T).set({ activityId: null }).where(templateId === null ? eq(T.activityId, activityId) : and(eq(T.activityId, activityId), ne(T.id, templateId)));
  if (templateId !== null) await conn.update(T).set({ activityId }).where(eq(T.id, templateId));
}

export async function createActivity(data: ActivityInput, actor: AuthUser): Promise<ActivityRow> {
  const now = new Date();
  const activity = await db.transaction(async (tx) => {
    const [ins] = await tx.insert(schema.activities).values({ ...(await attributes(data)), createdBy: actor.id, createdAt: now, updatedAt: now }).$returningId();
    await syncTargets(ins.id, data, tx);
    await recordAudit('activity.created', { type: 'activity', id: ins.id }, { name: data.name, date: data.date }, actor.id, tx);
    return (await tx.select().from(schema.activities).where(eq(schema.activities.id, ins.id)))[0];
  });
  await notifyRoster(activity, await resolveStudentIds(activity), actor);
  return activity;
}

/** Notify roster scouts and their approved parents, never the creator, never twice. */
export async function notifyRoster(activity: ActivityRow, studentIds: number[], creator: AuthUser | null) {
  if (!studentIds.length) return;
  const scouts = await db.select({ id: schema.users.id }).from(schema.users).where(inArray(schema.users.studentId, studentIds));
  const parents = await db.select({ id: schema.parentStudentLinks.parentUserId }).from(schema.parentStudentLinks)
    .where(and(inArray(schema.parentStudentLinks.studentId, studentIds), eq(schema.parentStudentLinks.status, 'approved')));
  const ids = new Set([...scouts, ...parents].map((r) => r.id));
  if (creator) ids.delete(creator.id);
  const users = (await loadUsers([...ids])).filter((u) => u.status === 'active');
  await sendMany(users, `New activity: ${activity.name}`, notify.activityBody(activity.name, activity.date), '/dashboard');
}

export async function updateActivity(activity: ActivityRow, data: ActivityInput, actor: AuthUser): Promise<void> {
  await db.transaction(async (tx) => {
    await tx.update(schema.activities).set({ ...(await attributes(data)), updatedAt: new Date() }).where(eq(schema.activities.id, activity.id));
    await syncTargets(activity.id, data, tx);
    await recordAudit('activity.updated', { type: 'activity', id: activity.id }, { name: data.name }, actor.id, tx);
  });
}

export async function deleteActivity(activity: ActivityRow, actor: AuthUser): Promise<void> {
  await recordAudit('activity.deleted', { type: 'activity', id: activity.id }, { name: activity.name }, actor.id);
  await db.update(schema.activities).set({ deletedAt: new Date() }).where(eq(schema.activities.id, activity.id));
}

export async function activityTargets(activityId: number) {
  const [sections, groups] = await Promise.all([
    db.select({ s: schema.activitySections.section }).from(schema.activitySections).where(eq(schema.activitySections.activityId, activityId)),
    db.select({ id: schema.groups.id, name: schema.groups.name }).from(schema.activityGroups).innerJoin(schema.groups, eq(schema.groups.id, schema.activityGroups.groupId)).where(eq(schema.activityGroups.activityId, activityId)),
  ]);
  return { sections: sections.map((r) => r.s), groups };
}

export function targetSummary(allStudents: boolean, sections: string[], groupNames: string[]): string {
  if (allStudents) return 'All scouts';
  const parts = [...sections, ...groupNames];
  return parts.length ? parts.join(', ') : 'No roster';
}

export interface ActivityFilters { q?: string; from?: string; to?: string; charged?: string; certificate?: string; page: number }

export async function listActivities(user: AuthUser, f: ActivityFilters) {
  const A = schema.activities;
  const where = and(
    isNull(A.deletedAt),
    f.q ? like(A.name, `%${f.q}%`) : undefined,
    f.from ? gte(A.date, f.from) : undefined,
    f.to ? lte(A.date, f.to) : undefined,
    f.charged ? eq(A.chargeFee, f.charged === 'yes') : undefined,
    f.certificate === 'yes' ? isNotNull(A.certificateTemplateId) : f.certificate === 'no' ? isNull(A.certificateTemplateId) : undefined,
    await activityScope(user),
  );
  const [{ n }] = await db.select({ n: count() }).from(A).where(where);
  const rows = await db.select({ activity: A, template: schema.certificateTemplates.name }).from(A)
    .leftJoin(schema.certificateTemplates, eq(schema.certificateTemplates.id, A.certificateTemplateId))
    .where(where).orderBy(desc(A.date), desc(A.id)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);

  const ids = rows.map((r) => r.activity.id);
  const marked = ids.length ? await db.select({ id: schema.attendanceRecords.activityId, n: count() }).from(schema.attendanceRecords).where(inArray(schema.attendanceRecords.activityId, ids)).groupBy(schema.attendanceRecords.activityId) : [];
  const sections = ids.length ? await db.select().from(schema.activitySections).where(inArray(schema.activitySections.activityId, ids)) : [];
  const groups = ids.length ? await db.select({ activityId: schema.activityGroups.activityId, name: schema.groups.name }).from(schema.activityGroups).innerJoin(schema.groups, eq(schema.groups.id, schema.activityGroups.groupId)).where(inArray(schema.activityGroups.activityId, ids)) : [];

  return pageOf(rows.map(({ activity, template }) => ({
    ...activity,
    template,
    marked: Number(marked.find((m) => m.id === activity.id)?.n ?? 0),
    target: targetSummary(activity.allStudents, sections.filter((s) => s.activityId === activity.id).map((s) => s.section), groups.filter((g) => g.activityId === activity.id).map((g) => g.name)),
  })), Number(n), f.page);
}
