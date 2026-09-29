'use server';

import { notFound, redirect } from 'next/navigation';
import { revalidatePath } from 'next/cache';
import { schema } from '@/db';
import { echo, handle, simple, type ActionState } from '@/server/action';
import { flash } from '@/server/flash';
import { ScoutError } from '@/server/errors';
import { exists, unique } from '@/server/rules';
import { requireAdmin, requireStaff } from '@/server/session';
import { storeSignature } from '@/server/signatures';
import { isUpload } from '@/server/uploads';
import { isPendingParent } from '@/server/user-queries';
import { createUser, deleteUser, rejectParentRegistration, resetPin, updateUser, verifyParentRegistration } from '@/server/user-admin';
import { loadUserByUuid, hasAnyRole } from '@/server/users';
import { normalizeNationalId } from '@/server/auth';
import { Permission, Role, UserStatus, type PermissionValue, type RoleValue } from '@/lib/enums';
import { inEnum, parseForm, validate } from '@/lib/validate';
import { recordAudit } from '@/server/audit';

const studentIdRules = (ignoreUserId?: number) => ['nullable', 'integer', exists(schema.students, schema.students.id), unique(schema.users, schema.users.studentId, ignoreUserId, schema.users.id)];

async function target(uuid: unknown) {
  const user = await loadUserByUuid(String(uuid ?? ''));
  if (!user) notFound();
  return user;
}

export async function createUserAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const actor = await requireAdmin();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate({ ...input, national_id: normalizeNationalId(input.national_id) }, {
      name: ['required', 'string', 'max:255'],
      national_id: ['required', 'string', 'max:64', unique(schema.users, schema.users.nationalId)],
      email: ['required', 'email', 'max:255', unique(schema.users, schema.users.email)],
      pin: ['required', 'string', 'min:4', 'max:32'],
      roles: ['required', 'array', 'min:1'],
      'roles.*': [inEnum(Role)],
      permissions: ['array'],
      'permissions.*': [inEnum(Permission)],
      student_id: studentIdRules(),
    });
    const user = await createUser({
      name: data.name, nationalId: data.national_id, email: data.email, pin: data.pin, roles: data.roles as RoleValue[],
      permissions: (data.permissions ?? []) as PermissionValue[], studentId: data.student_id ?? null,
    }, actor);
    await flash('success', `${user.name} was created.`);
    redirect(`/users/${user.uuid}`);
  }, echo(input));
}

export async function updateUserAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const actor = await requireAdmin();
  const input = parseForm(formData);
  const user = await target(input.uuid);
  return handle(async () => {
    const data = await validate(input, {
      name: ['required', 'string', 'max:255'],
      email: ['nullable', 'email', 'max:255', unique(schema.users, schema.users.email, user.id, schema.users.id)],
      status: ['required', inEnum(UserStatus)],
      roles: ['array'],
      'roles.*': [inEnum(Role)],
      permissions: ['array'],
      'permissions.*': [inEnum(Permission)],
      student_id: studentIdRules(user.id),
    });
    await updateUser(user.id, {
      name: data.name, email: data.email ?? null, status: data.status, roles: (data.roles ?? []) as RoleValue[],
      permissions: (data.permissions ?? []) as PermissionValue[], studentId: data.student_id ?? null,
    }, actor);
    await flash('success', 'The user was saved.');
    redirect(`/users/${user.uuid}`);
  }, echo(input));
}

export async function resetPinAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const actor = await requireAdmin();
  const input = parseForm(formData);
  const user = await target(input.uuid);
  return handle(async () => {
    const data = await validate(input, { pin: ['required', 'string', 'min:4', 'max:32'] });
    await resetPin(user.id, data.pin, actor);
    await flash('success', 'The PIN was reset and the user was signed out everywhere.');
    redirect(`/users/${user.uuid}`);
  });
}

export async function uploadSignatureAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const actor = await requireAdmin();
  const input = parseForm(formData);
  const user = await target(input.uuid);
  return handle(async () => {
    if (!hasAnyRole(user, ['admin', 'leader'])) throw new ScoutError('Signatures are only for leaders and admins.');
    if (!isUpload(input.signature)) throw new ScoutError('Choose a signature image to upload.');
    await storeSignature(user, input.signature);
    await recordAudit('user.signature_uploaded', { type: 'user', id: user.id }, {}, actor.id);
    await flash('success', 'The signature was uploaded.');
    redirect(`/users/${user.uuid}`);
  });
}

export async function deleteUserAction(formData: FormData): Promise<void> {
  const actor = await requireAdmin();
  const user = await target(formData.get('uuid'));
  await simple(async () => {
    await deleteUser(user.id, actor);
    await flash('success', 'The user was deleted.');
    redirect('/users');
  });
}

async function pendingParent(formData: FormData) {
  const actor = await requireStaff();
  const parent = await target(formData.get('uuid'));
  if (!(await isPendingParent(parent.id))) notFound();
  return { actor, parent };
}

export async function verifyParentAction(formData: FormData): Promise<void> {
  const { actor, parent } = await pendingParent(formData);
  await simple(async () => {
    await verifyParentRegistration(parent.id, actor);
    await flash('success', `${parent.name} is verified.`);
    revalidatePath('/parent-registrations');
  });
}

export async function rejectParentAction(formData: FormData): Promise<void> {
  const { actor, parent } = await pendingParent(formData);
  await simple(async () => {
    await rejectParentRegistration(parent.id, actor);
    await flash('success', `${parent.name}'s registration was declined.`);
    revalidatePath('/parent-registrations');
  });
}

