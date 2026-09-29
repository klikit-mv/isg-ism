import { and, asc, count, desc, eq, isNull, like, or, sql, type SQL } from 'drizzle-orm';
import { db, schema } from '@/db';
import { PAGE_SIZE, pageOf, type Page } from '@/components/Pagination';
import { studentScope } from './scope';
import type { AuthUser } from './users';

export type StudentRow = typeof schema.students.$inferSelect;

export const findStudentByUuid = async (uuid: string): Promise<StudentRow | null> => {
  const [row] = await db.select().from(schema.students).where(and(eq(schema.students.uuid, uuid), isNull(schema.students.deletedAt))).limit(1);
  return row ?? null;
};

export interface StudentFilters {
  q?: string;
  section?: string;
  status?: string;
  page?: number;
}

/** Scouts the person may see, pending registrations first. */
export async function listStudents(user: AuthUser, f: StudentFilters): Promise<Page<StudentRow>> {
  const S = schema.students;
  const term = f.q?.trim();
  const like_ = term ? `%${term}%` : null;
  const where = and(
    isNull(S.deletedAt),
    like_ ? or(like(S.name, like_), like(S.nationalId, like_), like(S.indexNumber, like_)) : undefined,
    f.section ? eq(S.section, f.section) : undefined,
    f.status ? eq(S.status, f.status) : undefined,
    await studentScope(user),
  );
  const page = f.page ?? 1;
  const [{ n }] = await db.select({ n: count() }).from(S).where(where);
  const rows = await db.select().from(S).where(where)
    .orderBy(sql`CASE WHEN ${S.status} = 'pending' THEN 0 ELSE 1 END`, asc(S.name))
    .limit(PAGE_SIZE).offset((page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), page);
}

export async function pendingCount(): Promise<number> {
  const [{ n }] = await db.select({ n: count() }).from(schema.students).where(and(eq(schema.students.status, 'pending'), isNull(schema.students.deletedAt)));
  return Number(n);
}

export async function groupNamesFor(studentId: number): Promise<string[]> {
  const rows = await db.select({ name: schema.groups.name }).from(schema.groupMembers)
    .innerJoin(schema.groups, eq(schema.groups.id, schema.groupMembers.groupId))
    .where(and(isNull(schema.groups.deletedAt), eq(schema.groupMembers.studentId, studentId))).orderBy(asc(schema.groups.name));
  return rows.map((r) => r.name);
}

/** The parent who currently holds a pending or approved link, if any. */
export async function assignedParent(studentId: number) {
  const [row] = await db.select({ name: schema.users.name, status: schema.parentStudentLinks.status }).from(schema.parentStudentLinks)
    .innerJoin(schema.users, eq(schema.users.id, schema.parentStudentLinks.parentUserId))
    .where(and(eq(schema.parentStudentLinks.studentId, studentId), or(eq(schema.parentStudentLinks.status, 'pending'), eq(schema.parentStudentLinks.status, 'approved')) as SQL)).limit(1);
  return row ?? null;
}

export async function certificatesOf(studentId: number, page = 1) {
  const C = schema.certificates;
  const [{ n }] = await db.select({ n: count() }).from(C).where(eq(C.studentId, studentId));
  const rows = await db.select().from(C).where(eq(C.studentId, studentId)).orderBy(desc(C.dateAwarded), desc(C.id)).limit(PAGE_SIZE).offset((page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), page);
}

export async function badgeRequestsOf(studentId: number, page = 1) {
  const R = schema.badgeRequests;
  const [{ n }] = await db.select({ n: count() }).from(R).where(eq(R.studentId, studentId));
  const rows = await db.select().from(R).where(eq(R.studentId, studentId)).orderBy(desc(R.createdAt), desc(R.id)).limit(PAGE_SIZE).offset((page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), page);
}

export async function leadershipOf(studentId: number, page = 1) {
  const L = schema.leadershipRecords;
  const [{ n }] = await db.select({ n: count() }).from(L).where(eq(L.studentId, studentId));
  const rows = await db.select({ record: L, certNumber: schema.certificates.certNumber }).from(L)
    .leftJoin(schema.certificates, eq(schema.certificates.id, L.certificateId))
    .where(eq(L.studentId, studentId)).orderBy(desc(L.startDate), desc(L.id)).limit(PAGE_SIZE).offset((page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), page);
}
