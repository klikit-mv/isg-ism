import { randomUUID } from 'node:crypto';
import { eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import type { PermissionValue, RoleValue } from '@/lib/enums';
import { assignRole, hashPin, loadUser, syncPermissions, type AuthUser } from '@/server/users';

let counter = 0;
const next = () => ++counter;

export const PIN = '1234';

export async function makeUser(opts: {
  roles?: RoleValue[];
  permissions?: PermissionValue[];
  status?: 'active' | 'inactive';
  nationalId?: string;
  name?: string;
  email?: string | null;
  pin?: string;
  studentId?: number | null;
  verified?: boolean;
} = {}): Promise<AuthUser> {
  const n = next();
  const now = new Date();
  const [row] = await db.insert(schema.users).values({
    name: opts.name ?? `User ${n}`,
    nationalId: opts.nationalId ?? `U${String(n).padStart(6, '0')}`,
    email: opts.email === undefined ? `user${n}@example.com` : opts.email,
    password: await hashPin(opts.pin ?? PIN),
    status: opts.status ?? 'active',
    studentId: opts.studentId ?? null,
    verifiedAt: opts.verified === false ? null : now,
    createdAt: now,
    updatedAt: now,
  }).$returningId();
  for (const role of opts.roles ?? []) await assignRole(row.id, role);
  if (opts.permissions?.length) await syncPermissions(row.id, opts.permissions);
  return (await loadUser(row.id))!;
}

export const makeAdmin = (o: Parameters<typeof makeUser>[0] = {}) => makeUser({ ...o, roles: ['admin'], name: o.name ?? 'Admin' });
export const makeLeader = (o: Parameters<typeof makeUser>[0] = {}) => makeUser({ ...o, roles: ['leader'] });

export type StudentRow = typeof schema.students.$inferSelect;

export async function makeStudent(o: Partial<typeof schema.students.$inferInsert> = {}): Promise<StudentRow> {
  const n = next();
  const now = new Date();
  const [row] = await db.insert(schema.students).values({
    indexNumber: `IX${String(n).padStart(5, '0')}`,
    name: `Scout ${n}`,
    nationalId: `A${String(1000000 + n)}`,
    email: `scout${n}@example.com`,
    gender: 'Male',
    permanentAddress: 'Addu', presentAddress: 'Addu', dateOfBirth: '2012-05-06',
    parentName: 'Parent Name', primaryMobile: '7770000',
    section: 'Scout', status: 'active', verifiedAt: now, createdAt: now, updatedAt: now,
    uuid: randomUUID(),
    ...o,
  }).$returningId();
  const [student] = await db.select().from(schema.students).where(eq(schema.students.id, row.id));
  return student;
}

/** A parent with approved links to the given scouts. */
export async function makeParentOf(...students: StudentRow[]): Promise<AuthUser> {
  const parent = await makeUser({ roles: ['parent'] });
  for (const s of students) {
    await db.insert(schema.parentStudentLinks).values({ parentUserId: parent.id, studentId: s.id, status: 'approved', createdAt: new Date(), updatedAt: new Date() });
  }
  return (await loadUser(parent.id))!;
}

/** A scout with their own sign-in account. */
export async function makeStudentUser(student?: StudentRow): Promise<AuthUser> {
  const s = student ?? (await makeStudent());
  return makeUser({ roles: ['student'], studentId: s.id, nationalId: s.nationalId, name: s.name });
}

export async function makeGroup(o: { name?: string; section?: string | null; leaders?: AuthUser[]; members?: StudentRow[] } = {}) {
  const now = new Date();
  const [row] = await db.insert(schema.groups).values({ name: o.name ?? `Group ${next()}`, section: o.section ?? null, createdAt: now, updatedAt: now }).$returningId();
  for (const l of o.leaders ?? []) await db.insert(schema.groupLeaders).values({ groupId: row.id, userId: l.id, createdAt: now, updatedAt: now });
  for (const m of o.members ?? []) await db.insert(schema.groupMembers).values({ groupId: row.id, studentId: m.id, createdAt: now, updatedAt: now });
  const [group] = await db.select().from(schema.groups).where(eq(schema.groups.id, row.id));
  return group;
}
