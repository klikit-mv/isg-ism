'use server';

import { and, eq, isNull, ne } from 'drizzle-orm';
import { forbidden, notFound, redirect } from 'next/navigation';
import { db, schema } from '@/db';
import { handle, echo, simple, type ActionState } from '@/server/action';
import { flash } from '@/server/flash';
import { createGroup, deleteGroup, findGroupByUuid, renameGroup, syncMembership } from '@/server/groups';
import { canAccessGroup } from '@/server/scope';
import { requireUser } from '@/server/session';
import { isActive, isAdmin, isLeader } from '@/server/users';
import { ScoutSection, type ScoutSectionValue } from '@/lib/enums';
import { inEnum, parseForm, validate, type Rules } from '@/lib/validate';
import { ScoutError } from '@/server/errors';

const groupRules: Rules = { name: ['required', 'string', 'max:255'], type: ['nullable', 'string', 'max:100'], section: ['nullable', inEnum(ScoutSection)] };

async function loadEditable(uuid: unknown) {
  const user = await requireUser();
  const group = await findGroupByUuid(String(uuid ?? ''));
  if (!group) notFound();
  if (!isActive(user) || !isLeader(user) && !isAdmin(user) || !(await canAccessGroup(user, group.id))) forbidden();
  return { user, group };
}

export async function createGroupAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await requireUser();
  if (!isAdmin(user)) forbidden();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(input, { ...groupRules });
    const group = await createGroup(data.name, data.type ?? null, user, (data.section as ScoutSectionValue | null) ?? null);
    await flash('success', 'The group was created.');
    redirect(`/groups/${group.uuid}`);
  }, echo(input));
}

export async function updateGroupAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const input = parseForm(formData);
  const { user, group } = await loadEditable(input.uuid);
  return handle(async () => {
    const data = await validate(input, { ...groupRules });
    const section = (data.section as ScoutSectionValue | null) ?? null;
    if (section) {
      const [outside] = await db.select({ id: schema.students.id }).from(schema.groupMembers)
        .innerJoin(schema.students, eq(schema.students.id, schema.groupMembers.studentId))
        .where(and(eq(schema.groupMembers.groupId, group.id), ne(schema.students.section, section), isNull(schema.students.deletedAt))).limit(1);
      if (outside) throw new ScoutError(`Some members are not in the ${section} section. Remove them first, then change the group's section.`);
    }
    await renameGroup(group, data.name, data.type ?? null, user, section);
    await flash('success', 'The group was saved.');
    redirect(`/groups/${group.uuid}`);
  }, echo(input));
}

export async function membershipAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const input = parseForm(formData);
  const { user, group } = await loadEditable(input.uuid);
  return handle(async () => {
    const data = await validate(input, {
      members: ['array'], 'members.*': ['integer'],
      leaders: ['array'], 'leaders.*': ['integer'],
      assistant_leaders: ['array'], 'assistant_leaders.*': ['integer'],
    });
    await syncMembership(group, data.members ?? [], data.leaders ?? [], data.assistant_leaders ?? [], user);
    await flash('success', 'Membership was saved.');
    redirect(`/groups/${group.uuid}`);
  });
}

export async function deleteGroupAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  if (!isAdmin(user)) forbidden();
  const group = await findGroupByUuid(String(formData.get('uuid') ?? ''));
  if (!group) notFound();
  await simple(async () => {
    await deleteGroup(group, user);
    await flash('success', 'The group was deleted.');
    redirect('/groups');
  });
}
