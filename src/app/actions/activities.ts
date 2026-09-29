'use server';

import { and, eq } from 'drizzle-orm';
import { forbidden, notFound, redirect } from 'next/navigation';
import { db, schema } from '@/db';
import { createActivity, deleteActivity, findActivityByUuid, updateActivity, type ActivityInput } from '@/server/activities';
import { echo, handle, simple, type ActionState } from '@/server/action';
import { ValidationError } from '@/server/errors';
import { flash } from '@/server/flash';
import { canAccessActivity } from '@/server/scope';
import { exists } from '@/server/rules';
import { requireUser } from '@/server/session';
import { isActive, isAdmin, isLeader } from '@/server/users';
import { ScoutSection } from '@/lib/enums';
import { inEnum, parseForm, validate } from '@/lib/validate';

const staffOnly = async () => {
  const user = await requireUser();
  if (!isActive(user) || !isLeader(user)) if (!isAdmin(user)) forbidden();
  return user;
};

async function validated(input: Record<string, unknown>): Promise<ActivityInput> {
  const data = await validate(input, {
    name: ['required', 'string', 'max:255'],
    date: ['required', 'date'],
    details: ['nullable', 'string', 'max:5000'],
    all_students: ['sometimes', 'boolean'],
    sections: ['array'],
    'sections.*': [inEnum(ScoutSection)],
    groups: ['array'],
    'groups.*': ['integer', exists(schema.groups, schema.groups.id)],
    charge_fee: ['sometimes', 'boolean'],
    fee_amount: ['nullable', 'numeric', 'min:0', 'max:9999999'],
    certificate_template_id: ['nullable', 'integer', async (value: number) => {
      const [row] = await db.select({ id: schema.certificateTemplates.id }).from(schema.certificateTemplates)
        .where(and(eq(schema.certificateTemplates.id, value), eq(schema.certificateTemplates.type, 'general'))).limit(1);
      return row ? null : 'The selected certificate template is invalid.';
    }],
  });
  const activity: ActivityInput = {
    name: data.name, date: data.date, details: data.details ?? null, all_students: !!data.all_students, charge_fee: !!data.charge_fee,
    fee_amount: data.fee_amount ?? null, sections: data.sections ?? [], groups: data.groups ?? [], certificate_template_id: data.certificate_template_id ?? null,
  };
  if (!activity.all_students && !activity.sections?.length && !activity.groups?.length) {
    throw new ValidationError({ sections: 'Choose who is expected: all scouts, sections or groups.' });
  }
  return activity;
}

export async function createActivityAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await staffOnly();
  const input = parseForm(formData);
  return handle(async () => {
    const activity = await createActivity(await validated(input), user);
    await flash('success', `${activity.name} was created and the roster was notified.`);
    redirect('/activities');
  }, echo(input));
}

export async function updateActivityAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await staffOnly();
  const input = parseForm(formData);
  const activity = await findActivityByUuid(String(input.uuid ?? ''));
  if (!activity) notFound();
  if (!(await canAccessActivity(user, activity.id))) forbidden();
  return handle(async () => {
    await updateActivity(activity, await validated(input), user);
    await flash('success', 'The activity was saved.');
    redirect('/activities');
  }, echo(input));
}

export async function deleteActivityAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  if (!isAdmin(user)) forbidden();
  const activity = await findActivityByUuid(String(formData.get('uuid') ?? ''));
  if (!activity) notFound();
  await simple(async () => {
    await deleteActivity(activity, user);
    await flash('success', 'The activity was deleted.');
    redirect('/activities');
  });
}
