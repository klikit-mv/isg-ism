import { eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import { validate } from '@/lib/validate';
import { recordAudit } from './audit';
import { normalizeNationalId } from './auth';
import { ScoutError } from './errors';
import { notify } from './notifications';
import { assertStudentHasNoOtherParent } from './parent-links';
import { unique } from './rules';
import { assignRole, hashPin } from './users';

/** Public parent registration: an inactive parent with pending links to their children. */
export async function registerParent(input: Record<string, unknown>) {
  const S = schema;
  const data = await validate(
    { ...input, national_id: normalizeNationalId(input.national_id) },
    {
      name: ['required', 'string', 'max:255'],
      national_id: ['required', 'string', 'max:64', unique(S.users, S.users.nationalId)],
      email: ['required', 'email', 'max:255', unique(S.users, S.users.email)],
      pin: ['required', 'string', 'min:4', 'max:32', 'confirmed'],
      children: ['required', 'array', 'min:1'],
      'children.*': ['required', 'string', 'max:64'],
    },
    { 'children.required': 'Add at least one child by National ID.' },
  );

  const now = new Date();
  const parent = await db.transaction(async (tx) => {
    const children: { id: number; nationalId: string }[] = [];
    for (const nationalId of new Set((data.children as string[]).map(normalizeNationalId))) {
      const [student] = await tx.select({ id: S.students.id, nationalId: S.students.nationalId }).from(S.students).where(eq(S.students.nationalId, nationalId)).limit(1);
      if (!student) throw new ScoutError(`No scout matches National ID ${nationalId}.`);
      await assertStudentHasNoOtherParent(student.id, undefined, tx);
      children.push(student);
    }
    if (!children.length) throw new ScoutError('Add at least one child by National ID.');

    const [inserted] = await tx.insert(S.users).values({
      name: data.name, nationalId: data.national_id, email: data.email, password: await hashPin(data.pin), status: 'inactive', createdAt: now, updatedAt: now,
    }).$returningId();
    await assignRole(inserted.id, 'parent', tx);
    for (const child of children) {
      await tx.insert(S.parentStudentLinks).values({ parentUserId: inserted.id, studentId: child.id, status: 'pending', createdAt: now, updatedAt: now });
    }
    await recordAudit('parent.registered', { type: 'user', id: inserted.id }, { children: children.map((c) => c.nationalId) }, inserted.id, tx);
    return { id: inserted.id, name: data.name as string };
  });

  await notify.parentRegistered(parent);
  return parent;
}
