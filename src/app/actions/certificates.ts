'use server';

import { eq } from 'drizzle-orm';
import { forbidden, notFound, redirect } from 'next/navigation';
import { revalidatePath } from 'next/cache';
import { db, schema } from '@/db';
import { echo, handle, simple, type ActionState } from '@/server/action';
import { createBadge, deleteBadge, findBadgeByUuid, updateBadge } from '@/server/badges';
import { createTemplate, deleteTemplate, findTemplateByUuid, setTemplateActive, updateTemplate } from '@/server/certificate-templates';
import {
  approveRequest, bulkCreateGeneral, canDecideRequest, canIssue, canManageCertificate, findCertificateByUuid, findRequestByUuid, generateApproved, generateGeneralCertificate,
  generateLeadershipCertificate, issueForActivityAttendance, regenerateCertificate, rejectRequest, requestBadge, signCertificate,
} from '@/server/certificates';
import { createLeadership, deleteLeadership, findLeadershipByUuid, updateLeadership } from '@/server/leadership';
import { ScoutError } from '@/server/errors';
import { flash } from '@/server/flash';
import { canAccessActivity, canAccessStudent } from '@/server/scope';
import { requireUser } from '@/server/session';
import { isActive, isAdmin, isLeader } from '@/server/users';
import { BADGE_CATEGORIES, CertificateType, ScoutSection } from '@/lib/enums';
import { isUpload } from '@/server/uploads';
import { inEnum, parseForm, validate } from '@/lib/validate';

async function issuer() {
  const user = await requireUser();
  if (!canIssue(user)) forbidden();
  return user;
}
async function admin() {
  const user = await requireUser();
  if (!isAdmin(user)) forbidden();
  return user;
}
async function staff() {
  const user = await requireUser();
  if (!isActive(user) || !(isAdmin(user) || isLeader(user))) forbidden();
  return user;
}
async function studentBy(uuid: string) {
  const [s] = await db.select().from(schema.students).where(eq(schema.students.uuid, uuid)).limit(1);
  return s ?? null;
}
const templateBy = async (uuid: unknown) => (uuid ? await findTemplateByUuid(String(uuid)) : null);

// ── Certificates ───────────────────────────────────────────────────────────

export async function issueCertificateAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await issuer();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(input, {
      student: ['required', 'uuid'], title: ['required', 'string', 'max:255'], date_awarded: ['required', 'date'], template: ['nullable', 'uuid'], activity: ['nullable', 'uuid'],
    });
    const student = await studentBy(data.student);
    if (!student) throw new ScoutError('Choose a scout.', 'student');
    if (!(await canAccessStudent(user, student))) forbidden();
    const [activity] = data.activity ? await db.select().from(schema.activities).where(eq(schema.activities.uuid, data.activity)).limit(1) : [];
    const template = await templateBy(data.template);
    if (!template && !activity) throw new ScoutError('Choose a template.', 'template');
    const cert = await generateGeneralCertificate(student, data.title, data.date_awarded, template, user, activity ?? null);
    await flash('success', `Certificate ${cert.certNumber} was issued.`);
    redirect(`/certificates/${cert.uuid}`);
  }, echo(input));
}

export async function bulkIssueAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await issuer();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(input, { students: ['required', 'array', 'min:1'], 'students.*': ['integer'], title: ['required', 'string', 'max:255'], date_awarded: ['required', 'date'], template: ['required', 'uuid'] });
    const template = await templateBy(data.template);
    if (!template) throw new ScoutError('Choose a template.', 'template');
    const result = await bulkCreateGeneral(data.students as number[], data.title, data.date_awarded, template, user);
    const failed = Object.entries(result.failed);
    if (failed.length) await flash('warning', `${result.created.length} certificate(s) issued. ${failed.length} could not be issued — ${failed.map(([n, r]) => `${n}: ${r}`).join('; ')}`);
    else await flash('success', `${result.created.length} certificate(s) issued.`);
    redirect('/certificates');
  }, echo(input));
}

export async function issueActivityAction(formData: FormData): Promise<void> {
  const user = await issuer();
  const [activity] = await db.select().from(schema.activities).where(eq(schema.activities.uuid, String(formData.get('uuid') ?? ''))).limit(1);
  if (!activity) notFound();
  if (!(await canAccessActivity(user, activity.id))) forbidden();
  await simple(async () => {
    const result = await issueForActivityAttendance(activity, user);
    const failed = Object.keys(result.failed).length;
    await flash(failed ? 'warning' : 'success', `${result.issued} certificate(s) issued for ${activity.name}.${failed ? ` ${failed} could not be issued: ${[...new Set(Object.values(result.failed))].join('; ')}` : ''}`);
    revalidatePath('/certificates');
  });
}

async function manageable(formData: FormData) {
  const user = await requireUser();
  const cert = await findCertificateByUuid(String(formData.get('uuid') ?? ''));
  if (!cert) notFound();
  if (!(await canManageCertificate(user, cert))) forbidden();
  return { user, cert };
}
export async function regenerateCertificateAction(formData: FormData): Promise<void> {
  const { user, cert } = await manageable(formData);
  await simple(async () => { await regenerateCertificate(cert, user); await flash('success', 'The certificate PDF was regenerated.'); revalidatePath('/certificates', 'layout'); });
}
export async function signCertificateAction(formData: FormData): Promise<void> {
  const { user, cert } = await manageable(formData);
  await simple(async () => { await signCertificate(cert, user); await flash('success', 'The certificate is verified and your signature was applied.'); revalidatePath('/certificates', 'layout'); });
}

// ── Badges ─────────────────────────────────────────────────────────────────

const badgeRules = {
  name: ['required', 'string', 'max:255'],
  code: ['required', 'string', 'max:50'],
  section: ['nullable', inEnum(ScoutSection)],
  category: ['nullable', 'string', 'in:' + Object.keys(BADGE_CATEGORIES).join(',')],
  description: ['nullable', 'string', 'max:1000'],
  certificate_template_id: ['nullable', 'integer'],
  number_prefix: ['nullable', 'string', 'max:20'],
  next_number: ['nullable', 'integer', 'min:1', 'max:99999'],
};

export async function saveBadgeAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await staff();
  const input = parseForm(formData);
  const badge = input.uuid ? await findBadgeByUuid(String(input.uuid)) : null;
  if (input.uuid && !badge) notFound();
  return handle(async () => {
    const data = await validate(input, badgeRules as never);
    const image = isUpload(input.image) ? input.image : null;
    if (badge) await updateBadge(badge, data as never, image, user);
    else await createBadge(data as never, image, user);
    await flash('success', `${data.name} was saved.`);
    revalidatePath('/badges');
  }, echo(input));
}

export async function deleteBadgeAction(formData: FormData): Promise<void> {
  const user = await staff();
  const badge = await findBadgeByUuid(String(formData.get('uuid') ?? ''));
  if (!badge) notFound();
  await simple(async () => { await deleteBadge(badge, user); await flash('success', 'The badge was deleted.'); revalidatePath('/badges'); });
}

// ── Badge requests ─────────────────────────────────────────────────────────

export async function requestBadgeAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await requireUser();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(input, { student: ['required', 'uuid'], badge: ['required', 'uuid'] });
    const student = await studentBy(data.student);
    const badge = await findBadgeByUuid(data.badge);
    if (!student || !badge) throw new ScoutError('Choose a scout and a badge.');
    const request = await requestBadge(student, badge, user);
    await flash('success', `Request ${request.requestId} was sent for approval.`);
    redirect(`/badge-requests/${request.uuid}`);
  }, echo(input));
}

async function decidable(formData: FormData) {
  const user = await requireUser();
  const request = await findRequestByUuid(String(formData.get('uuid') ?? ''));
  if (!request) notFound();
  if (!(await canDecideRequest(user, request))) forbidden();
  return { user, request };
}
const noteOf = async (formData: FormData) => (await validate({ note: formData.get('note') }, { note: ['nullable', 'string', 'max:255'] })).note ?? null;

export async function approveRequestAction(formData: FormData): Promise<void> {
  const { user, request } = await decidable(formData);
  await simple(async () => { await approveRequest(request, user, await noteOf(formData)); await flash('success', 'The badge request was approved.'); revalidatePath('/badge-requests', 'layout'); });
}
export async function rejectRequestAction(formData: FormData): Promise<void> {
  const { user, request } = await decidable(formData);
  await simple(async () => { await rejectRequest(request, user, await noteOf(formData)); await flash('success', 'The badge request was rejected.'); revalidatePath('/badge-requests', 'layout'); });
}
export async function generateRequestAction(formData: FormData): Promise<void> {
  const { user, request } = await decidable(formData);
  let target: string | null = null;
  await simple(async () => {
    const data = await validate({ date_awarded: formData.get('date_awarded'), template: formData.get('template') || null }, { date_awarded: ['required', 'date'], template: ['nullable', 'uuid'] });
    const cert = await generateApproved(request, user, data.date_awarded, await templateBy(data.template));
    await flash('success', `Certificate ${cert.certNumber} was generated.`);
    target = `/certificates/${cert.uuid}`;
  });
  if (target) redirect(target);
}

// ── Templates ──────────────────────────────────────────────────────────────

export async function saveTemplateAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await admin();
  const input = parseForm(formData);
  const template = input.uuid ? await findTemplateByUuid(String(input.uuid)) : null;
  if (input.uuid && !template) notFound();
  return handle(async () => {
    const data = await validate(input, { name: ['required', 'string', 'max:255'], type: ['required', inEnum(CertificateType)], activity: ['nullable', 'uuid'], active: ['sometimes', 'boolean'] });
    const [activity] = data.activity ? await db.select({ id: schema.activities.id }).from(schema.activities).where(eq(schema.activities.uuid, data.activity)).limit(1) : [];
    const payload = { name: data.name, type: data.type, activity_id: activity?.id ?? null, active: input.active === undefined && template ? false : (data.active ?? true) };
    if (template) await updateTemplate(template, payload, user);
    else await createTemplate(payload, user);
    await flash('success', `${data.name} was saved.`);
    revalidatePath('/certificate-templates');
  }, echo(input));
}

export async function toggleTemplateAction(formData: FormData): Promise<void> {
  const user = await admin();
  const template = await findTemplateByUuid(String(formData.get('uuid') ?? ''));
  if (!template) notFound();
  await simple(async () => { await setTemplateActive(template, !template.active, user); revalidatePath('/certificate-templates'); });
}
export async function deleteTemplateAction(formData: FormData): Promise<void> {
  const user = await admin();
  const template = await findTemplateByUuid(String(formData.get('uuid') ?? ''));
  if (!template) notFound();
  await simple(async () => { await deleteTemplate(template, user); await flash('success', 'The template was deleted.'); revalidatePath('/certificate-templates'); });
}

// ── Leadership ─────────────────────────────────────────────────────────────

export async function saveLeadershipAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await staff();
  const input = parseForm(formData);
  const record = input.uuid ? await findLeadershipByUuid(String(input.uuid)) : null;
  if (input.uuid && !record) notFound();
  return handle(async () => {
    const data = await validate(input, {
      student: ['required', 'uuid'], patrol_or_six: ['required', 'string', 'max:255'], troop_or_group: ['nullable', 'string', 'max:255'], start_date: ['required', 'date'], end_date: ['nullable', 'date'],
    });
    const student = await studentBy(data.student);
    if (!student) throw new ScoutError('Choose a scout.', 'student');
    if (!(await canAccessStudent(user, student))) forbidden();
    if (data.end_date && data.end_date < data.start_date) throw new ScoutError('The end date must be after the start date.', 'end_date');
    const payload = { student_id: student.id, patrol_or_six: data.patrol_or_six, troop_or_group: data.troop_or_group, start_date: data.start_date, end_date: data.end_date };
    if (record) await updateLeadership(record, payload, user);
    else await createLeadership(payload, user);
    await flash('success', 'The leadership record was saved.');
    revalidatePath('/leadership');
  }, echo(input));
}

async function leadershipFor(formData: FormData) {
  const user = await staff();
  const record = await findLeadershipByUuid(String(formData.get('uuid') ?? ''));
  if (!record) notFound();
  const [student] = await db.select().from(schema.students).where(eq(schema.students.id, record.studentId));
  if (!(await canAccessStudent(user, student))) forbidden();
  return { user, record };
}
export async function deleteLeadershipAction(formData: FormData): Promise<void> {
  const { user, record } = await leadershipFor(formData);
  await simple(async () => { await deleteLeadership(record, user); await flash('success', 'The record was deleted.'); revalidatePath('/leadership'); });
}
export async function generateLeadershipAction(formData: FormData): Promise<void> {
  const { user, record } = await leadershipFor(formData);
  await simple(async () => {
    const cert = await generateLeadershipCertificate(record.id, user);
    await flash('success', `Certificate ${cert.certNumber} is ready.`);
    revalidatePath('/leadership');
  });
}
