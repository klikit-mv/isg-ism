import { and, eq, inArray, isNull } from 'drizzle-orm';
import { db, schema } from '@/db';
import { Gender, ScoutSection } from '@/lib/enums';
import { validate, inEnum, type Rules } from '@/lib/validate';
import { recordAudit } from './audit';
import { normalizeNationalId } from './auth';
import { notify } from './notifications';
import { unique } from './rules';
import { ScoutError } from './errors';
import { assignRole, hashPin, loadUser, type AuthUser } from './users';
import { sectionNext, type ScoutSectionValue } from '@/lib/enums';

export type StudentRow = typeof schema.students.$inferSelect;

export const STUDENT_FIELDS = [
  'index_number', 'national_id', 'name', 'email', 'gender', 'permanent_address', 'present_address',
  'date_of_birth', 'parent_name', 'primary_mobile', 'secondary_mobile', 'section', 'class_name', 'patrol', 'status',
] as const;

/** The user account (if any) that belongs to a scout. */
export async function userIdForStudent(studentId: number): Promise<number | null> {
  const [row] = await db.select({ id: schema.users.id }).from(schema.users).where(eq(schema.users.studentId, studentId)).limit(1);
  return row?.id ?? null;
}

/** Shared scout validation rules: public registration, enrolment, edits and import. */
export async function studentRules(opts: { student?: StudentRow | null; publicRegistration?: boolean } = {}): Promise<Rules> {
  const { student = null, publicRegistration = false } = opts;
  const userId = student ? await userIdForStudent(student.id) : null;
  const S = schema;
  const rules: Rules = {
    index_number: ['required', 'string', 'max:50', unique(S.students, S.students.indexNumber, student?.id, S.students.id)],
    national_id: ['required', 'string', 'max:64', unique(S.students, S.students.nationalId, student?.id, S.students.id), unique(S.users, S.users.nationalId, userId, S.users.id)],
    name: ['required', 'string', 'max:255'],
    email: ['required', 'email', 'max:255', unique(S.students, S.students.email, student?.id, S.students.id), unique(S.users, S.users.email, userId, S.users.id)],
    gender: ['required', inEnum(Gender)],
    permanent_address: ['required', 'string', 'max:255'],
    present_address: ['required', 'string', 'max:255'],
    date_of_birth: ['required', 'date', 'before:today'],
    parent_name: ['required', 'string', 'max:255'],
    primary_mobile: ['required', 'string', 'max:30'],
    secondary_mobile: ['nullable', 'string', 'max:30'],
    section: ['required', inEnum(ScoutSection)],
  };
  if (publicRegistration) {
    rules.pin = ['required', 'string', 'min:4', 'max:32', 'confirmed'];
    return rules;
  }
  rules.class_name = ['nullable', 'string', 'max:100'];
  rules.patrol = ['nullable', 'string', 'max:100'];
  rules.status = ['required', inEnum({ values: ['active', 'pending', 'inactive'], label: (v) => v })];
  if (!student) rules.pin = ['nullable', 'string', 'min:4', 'max:32'];
  return rules;
}

export function prepareStudentInput(input: Record<string, unknown>): Record<string, unknown> {
  return input.national_id != null ? { ...input, national_id: normalizeNationalId(input.national_id) } : input;
}

const COLUMN_MAP: Record<string, keyof typeof schema.students.$inferInsert> = {
  index_number: 'indexNumber', national_id: 'nationalId', name: 'name', email: 'email', gender: 'gender',
  permanent_address: 'permanentAddress', present_address: 'presentAddress', date_of_birth: 'dateOfBirth',
  parent_name: 'parentName', primary_mobile: 'primaryMobile', secondary_mobile: 'secondaryMobile',
  section: 'section', class_name: 'className', patrol: 'patrol', status: 'status',
};

/** Column values for a students row from validated snake_case data (only the keys given). */
export function studentColumns(data: Record<string, any>): Partial<typeof schema.students.$inferInsert> {
  const out: Record<string, unknown> = {};
  for (const [key, column] of Object.entries(COLUMN_MAP)) {
    if (!(key in data)) continue;
    out[column] = key === 'national_id' ? normalizeNationalId(data[key]) : data[key] ?? null;
  }
  return out as Partial<typeof schema.students.$inferInsert>;
}

/** Public self-registration: a pending scout and an inactive sign-in account. */
export async function registerStudent(input: Record<string, unknown>): Promise<StudentRow> {
  const data = await validate(prepareStudentInput(input), await studentRules({ publicRegistration: true }));
  const now = new Date();

  const student = await db.transaction(async (tx) => {
    const [inserted] = await tx.insert(schema.students).values({ ...(studentColumns(data) as typeof schema.students.$inferInsert), status: 'pending', createdAt: now, updatedAt: now }).$returningId();
    const [row] = await tx.select().from(schema.students).where(eq(schema.students.id, inserted.id));

    const [u] = await tx.insert(schema.users).values({
      name: row.name, nationalId: row.nationalId, email: row.email, password: await hashPin(String(data.pin)),
      status: 'inactive', studentId: row.id, createdAt: now, updatedAt: now,
    }).$returningId();
    await assignRole(u.id, 'student', tx);
    await recordAudit('student.registered', { type: 'student', id: row.id }, { national_id: row.nationalId, section: row.section }, u.id, tx);
    return row;
  });

  await notify.studentRegistered(student);
  return student;
}

/** Verify a pending registration (leader or admin action). */
export async function verifyRegistration(studentId: number, actorId: number): Promise<void> {
  const now = new Date();
  await db.transaction(async (tx) => {
    await tx.update(schema.students).set({ status: 'active', verifiedAt: now, verifiedBy: actorId, updatedAt: now }).where(eq(schema.students.id, studentId));
    await tx.update(schema.users).set({ status: 'active', verifiedAt: now, verifiedBy: actorId, updatedAt: now }).where(eq(schema.users.studentId, studentId));
    await recordAudit('student.verified', { type: 'student', id: studentId }, {}, actorId, tx);
  });
  const userId = await userIdForStudent(studentId);
  await notify.studentVerified(userId ? await loadUser(userId) : null);
}

export const temporaryPin = () => String(Math.floor(Math.random() * 10000)).padStart(4, '0');

async function studentById(id: number, conn: typeof db = db): Promise<StudentRow> {
  const [row] = await conn.select().from(schema.students).where(eq(schema.students.id, id));
  return row;
}

/** Admin enrolment. Returns the scout and the PIN that was set. */
export async function createStudent(data: Record<string, any>, actor: AuthUser | null): Promise<{ student: StudentRow; pin: string }> {
  const pin = data.pin ? String(data.pin) : temporaryPin();
  const now = new Date();
  return db.transaction(async (tx) => {
    const columns = studentColumns(data);
    const status = (columns.status as string | undefined) ?? 'active';
    const [ins] = await tx.insert(schema.students).values({
      ...(columns as typeof schema.students.$inferInsert), status,
      verifiedAt: status === 'active' ? now : null, verifiedBy: status === 'active' ? actor?.id ?? null : null, createdAt: now, updatedAt: now,
    }).$returningId();
    const student = await studentById(ins.id, tx as unknown as typeof db);

    const [u] = await tx.insert(schema.users).values({
      name: student.name, nationalId: student.nationalId, email: student.email, password: await hashPin(pin),
      status: status === 'active' ? 'active' : 'inactive', studentId: student.id, verifiedAt: student.verifiedAt, verifiedBy: actor?.id ?? null, createdAt: now, updatedAt: now,
    }).$returningId();
    await assignRole(u.id, 'student', tx);
    await recordAudit('student.created', { type: 'student', id: student.id }, { national_id: student.nationalId, section: student.section }, actor?.id ?? null, tx);
    await recordAudit('user.created', { type: 'user', id: u.id }, { national_id: student.nationalId, roles: ['student'] }, actor?.id ?? null, tx);
    return { student, pin };
  });
}

export async function rejectRegistration(studentId: number, actor: AuthUser): Promise<void> {
  const now = new Date();
  await db.transaction(async (tx) => {
    await tx.update(schema.students).set({ status: 'inactive', updatedAt: now }).where(eq(schema.students.id, studentId));
    await tx.update(schema.users).set({ status: 'inactive', verifiedAt: now, verifiedBy: actor.id, updatedAt: now }).where(eq(schema.users.studentId, studentId));
    await recordAudit('student.declined', { type: 'student', id: studentId }, {}, actor.id, tx);
  });
}

/** The account mirrors name, National ID, email and active/inactive status. */
async function mirrorAccount(student: StudentRow, conn: typeof db): Promise<void> {
  const set: Record<string, unknown> = { name: student.name, nationalId: student.nationalId, email: student.email, updatedAt: new Date() };
  if (student.status === 'active') set.status = 'active';
  else if (student.status === 'inactive') set.status = 'inactive';
  await conn.update(schema.users).set(set).where(eq(schema.users.studentId, student.id));
}

export async function updateStudent(studentId: number, data: Record<string, any>, actor: AuthUser): Promise<StudentRow> {
  return db.transaction(async (tx) => {
    const before = await studentById(studentId, tx as unknown as typeof db);
    const columns = studentColumns(data);
    const changed = Object.entries(columns).filter(([k, v]) => (before as any)[k] !== v).map(([k]) => k);
    await tx.update(schema.students).set({ ...columns, updatedAt: new Date() }).where(eq(schema.students.id, studentId));
    const student = await studentById(studentId, tx as unknown as typeof db);
    await mirrorAccount(student, tx as unknown as typeof db);
    await recordAudit('student.updated', { type: 'student', id: studentId }, { changed }, actor.id, tx);
    return student;
  });
}

export async function setStudentStatus(studentId: number, status: 'active' | 'pending' | 'inactive', actor: AuthUser): Promise<void> {
  await db.transaction(async (tx) => {
    await tx.update(schema.students).set({ status, updatedAt: new Date() }).where(eq(schema.students.id, studentId));
    await mirrorAccount(await studentById(studentId, tx as unknown as typeof db), tx as unknown as typeof db);
    await recordAudit('student.status_changed', { type: 'student', id: studentId }, { status }, actor.id, tx);
  });
}

export async function deleteStudent(studentId: number, actor: AuthUser): Promise<void> {
  const now = new Date();
  await db.transaction(async (tx) => {
    const student = await studentById(studentId, tx as unknown as typeof db);
    await recordAudit('student.deleted', { type: 'student', id: studentId }, { national_id: student.nationalId }, actor.id, tx);
    await tx.update(schema.users).set({ deletedAt: now }).where(eq(schema.users.studentId, studentId));
    await tx.update(schema.students).set({ deletedAt: now }).where(eq(schema.students.id, studentId));
  });
}

/** Move scouts one section forward. Issued certificates are never rewritten. */
export async function bulkPromote(studentIds: number[], from: ScoutSectionValue, to: ScoutSectionValue, actor: AuthUser): Promise<{ promoted: number; skipped: number }> {
  if (sectionNext(from) !== to) {
    throw new ScoutError(`Scouts can only move one section forward, from ${from} to ${sectionNext(from) ?? 'nowhere'}.`);
  }
  const unique = [...new Set(studentIds)];
  return db.transaction(async (tx) => {
    const rows = unique.length ? await tx.select().from(schema.students).where(and(inArray(schema.students.id, unique), isNull(schema.students.deletedAt))).for('update') : [];
    let promoted = 0;
    let skipped = unique.length - rows.length;
    for (const student of rows) {
      if (student.section !== from) { skipped++; continue; }
      await tx.update(schema.students).set({ section: to, updatedAt: new Date() }).where(eq(schema.students.id, student.id));
      await recordAudit('student.promoted', { type: 'student', id: student.id }, { from, to }, actor.id, tx);
      promoted++;
    }
    return { promoted, skipped };
  });
}
