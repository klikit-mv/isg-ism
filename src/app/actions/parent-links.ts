'use server';

import { eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { db, schema } from '@/db';
import { handle, simple, type ActionState } from '@/server/action';
import { flash } from '@/server/flash';
import { createLink, setLinkStatus } from '@/server/parent-links';
import { exists } from '@/server/rules';
import { requireAdmin } from '@/server/session';
import { loadUser } from '@/server/users';
import { ParentLinkStatus } from '@/lib/enums';
import { inEnum, parseForm, validate } from '@/lib/validate';
import { ScoutError } from '@/server/errors';

export async function createLinkAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const actor = await requireAdmin();
  return handle(async () => {
    const data = await validate(parseForm(formData), {
      parent_user_id: ['required', 'integer', exists(schema.users, schema.users.id)],
      student_id: ['required', 'integer', exists(schema.students, schema.students.id)],
      status: ['nullable', inEnum(ParentLinkStatus)],
    });
    const parent = await loadUser(data.parent_user_id);
    const [student] = await db.select().from(schema.students).where(eq(schema.students.id, data.student_id));
    if (!parent || !student) throw new ScoutError('That parent or scout no longer exists.');
    await createLink(parent, student, (data.status ?? 'approved') as 'approved', actor);
    await flash('success', 'The parent link was saved.');
    revalidatePath('/parent-links');
  });
}

export async function updateLinkStatusAction(formData: FormData): Promise<void> {
  const actor = await requireAdmin();
  await simple(async () => {
    const data = await validate({ id: formData.get('id'), status: formData.get('status') }, { id: ['required', 'integer'], status: ['required', inEnum(ParentLinkStatus)] });
    await setLinkStatus(data.id, data.status, actor);
    await flash('success', 'The link status was changed.');
    revalidatePath('/parent-links');
  });
}
