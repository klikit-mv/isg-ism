import { and, asc, count, eq, inArray, isNull, like, or } from 'drizzle-orm';
import { db, schema } from '@/db';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import type { RoleValue } from '@/lib/enums';
import { loadUsers } from './users';

export async function listUsers(f: { q?: string; role?: string; status?: string; page: number }) {
  const U = schema.users;
  const term = f.q?.trim();
  const where = and(
    isNull(U.deletedAt),
    term ? or(like(U.name, `%${term}%`), like(U.nationalId, `%${term}%`), like(U.email, `%${term}%`)) : undefined,
    f.status ? eq(U.status, f.status) : undefined,
    f.role ? inArray(U.id, db.select({ id: schema.userRoles.userId }).from(schema.userRoles).where(eq(schema.userRoles.role, f.role))) : undefined,
  );
  const [{ n }] = await db.select({ n: count() }).from(U).where(where);
  const ids = (await db.select({ id: U.id }).from(U).where(where).orderBy(asc(U.name)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE)).map((r) => r.id);
  const users = await loadUsers(ids);
  users.sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id));
  return pageOf(users, Number(n), f.page);
}

export async function studentOptions(): Promise<{ value: string; label: string }[]> {
  const rows = await db.select({ id: schema.students.id, name: schema.students.name, nationalId: schema.students.nationalId }).from(schema.students)
    .where(isNull(schema.students.deletedAt)).orderBy(asc(schema.students.name));
  return rows.map((s) => ({ value: String(s.id), label: `${s.name} (${s.nationalId})` }));
}

/** Inactive, never-verified parents with the children they asked for. */
export async function pendingParents(page: number) {
  const U = schema.users;
  const where = and(
    isNull(U.deletedAt), eq(U.status, 'inactive'), isNull(U.verifiedAt),
    inArray(U.id, db.select({ id: schema.userRoles.userId }).from(schema.userRoles).where(eq(schema.userRoles.role, 'parent' satisfies RoleValue))),
  );
  const [{ n }] = await db.select({ n: count() }).from(U).where(where);
  const parents = await db.select().from(U).where(where).orderBy(asc(U.createdAt)).limit(PAGE_SIZE).offset((page - 1) * PAGE_SIZE);
  const links = parents.length
    ? await db.select({ userId: schema.parentStudentLinks.parentUserId, status: schema.parentStudentLinks.status, name: schema.students.name, nationalId: schema.students.nationalId })
        .from(schema.parentStudentLinks).innerJoin(schema.students, eq(schema.students.id, schema.parentStudentLinks.studentId))
        .where(inArray(schema.parentStudentLinks.parentUserId, parents.map((p) => p.id)))
    : [];
  return pageOf(parents.map((p) => ({ ...p, links: links.filter((l) => l.userId === p.id) })), Number(n), page);
}

export async function isPendingParent(userId: number): Promise<boolean> {
  const U = schema.users;
  const [row] = await db.select({ id: U.id }).from(U).where(and(
    eq(U.id, userId), isNull(U.deletedAt), eq(U.status, 'inactive'), isNull(U.verifiedAt),
    inArray(U.id, db.select({ id: schema.userRoles.userId }).from(schema.userRoles).where(eq(schema.userRoles.role, 'parent'))),
  )).limit(1);
  return !!row;
}
