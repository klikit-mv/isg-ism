'use server';

import { forbidden, notFound, redirect } from 'next/navigation';
import { revalidatePath } from 'next/cache';
import { ScoutError } from '@/server/errors';
import { echo, handle, simple, type ActionState } from '@/server/action';
import { flash } from '@/server/flash';
import { canChangePhoto, canManageStudents, canVerifyRegistrations } from '@/server/policies';
import { clearStudentPhoto, assignStudentPhoto } from '@/server/photos';
import { findStudentByUuid } from '@/server/student-queries';
import { bulkPromote, createStudent, deleteStudent, prepareStudentInput, rejectRegistration, studentRules, updateStudent, verifyRegistration } from '@/server/students';
import { isUpload } from '@/server/uploads';
import { requireUser } from '@/server/session';
import { inEnum, parseForm, validate } from '@/lib/validate';
import { ScoutSection, type ScoutSectionValue } from '@/lib/enums';

async function load(uuid: unknown) {
  const student = await findStudentByUuid(String(uuid ?? ''));
  if (!student) notFound();
  return student;
}

export async function createStudentAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await requireUser();
  if (!canManageStudents(user)) forbidden();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(prepareStudentInput(input), await studentRules());
    const photo = input.photo;
    const result = await createStudent(data, user);
    if (isUpload(photo)) await assignStudentPhoto(result.student, photo, user);
    let message = `${result.student.name} was enrolled.`;
    if (!data.pin) message += ` Their temporary PIN is ${result.pin} — share it with them privately.`;
    await flash('success', message);
    redirect(`/students/${result.student.uuid}`);
  }, echo(input));
}

export async function updateStudentAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await requireUser();
  if (!canManageStudents(user)) forbidden();
  const input = parseForm(formData);
  const student = await load(input.uuid);
  return handle(async () => {
    const data = await validate(prepareStudentInput(input), await studentRules({ student }));
    await updateStudent(student.id, data, user);
    if (isUpload(input.photo)) await assignStudentPhoto(student, input.photo, user);
    else if (input.remove_photo === '1') await clearStudentPhoto(student, user);
    await flash('success', 'The scout was saved.');
    redirect(`/students/${student.uuid}`);
  }, echo(input));
}

export async function deleteStudentAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  if (!canManageStudents(user)) forbidden();
  const student = await load(formData.get('uuid'));
  await simple(async () => {
    await deleteStudent(student.id, user);
    await flash('success', 'The scout was deleted.');
    redirect('/students');
  });
}

export async function verifyStudentAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  if (!canVerifyRegistrations(user)) forbidden();
  const student = await load(formData.get('uuid'));
  await simple(async () => {
    if (student.status !== 'pending') throw new ScoutError('Only pending registrations can be verified.');
    await verifyRegistration(student.id, user.id);
    await flash('success', `${student.name} is verified and can now sign in.`);
    revalidatePath('/students');
  });
}

export async function rejectStudentAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  if (!canVerifyRegistrations(user)) forbidden();
  const student = await load(formData.get('uuid'));
  await simple(async () => {
    if (student.status !== 'pending') throw new ScoutError('Only pending registrations can be declined.');
    await rejectRegistration(student.id, user);
    await flash('success', `${student.name}'s registration was declined.`);
    revalidatePath('/students');
  });
}

export async function studentPhotoAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  const student = await load(formData.get('uuid'));
  if (!(await canChangePhoto(user, student))) forbidden();
  await simple(async () => {
    if (formData.get('remove') === '1') {
      await clearStudentPhoto(student, user);
      await flash('success', 'The photo was removed.');
      return;
    }
    const photo = formData.get('photo');
    if (!isUpload(photo)) throw new ScoutError('Choose a photo to upload.');
    await assignStudentPhoto(student, photo, user);
    await flash('success', 'The photo was updated.');
  });
}

export async function promoteAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  if (!canManageStudents(user)) forbidden();
  await simple(async () => {
    const input = parseForm(formData);
    const data = await validate(input, {
      from: ['required', inEnum(ScoutSection)],
      to: ['required', inEnum(ScoutSection)],
      students: ['required', 'array', 'min:1'],
      'students.*': ['required', 'integer'],
    }, { 'students.required': 'Select at least one scout to promote.' });
    const result = await bulkPromote(data.students as number[], data.from as ScoutSectionValue, data.to as ScoutSectionValue, user);
    await flash('success', `${result.promoted} scout${result.promoted === 1 ? '' : 's'} moved to ${data.to}.${result.skipped ? ` ${result.skipped} skipped.` : ''}`);
    redirect(`/students/promote?from=${encodeURIComponent(data.from)}`);
  });
}

