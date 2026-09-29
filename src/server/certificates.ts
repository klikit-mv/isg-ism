import { randomBytes } from 'node:crypto';
import { and, asc, count, desc, eq, gte, inArray, isNotNull, isNull, like, lte, or } from 'drizzle-orm';
import { db, schema, type DbLike } from '@/db';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import { formatLongDate, startOfToday, todayLocal } from '@/lib/dates';
import { recordAudit } from './audit';
import { nextBadgeNumber, nextGeneralNumber, nextLeadershipNumber } from './certificate-numbers';
import { renderCertificatePdf, type CertificateSheet } from './certificate-pdf';
import { NotAccessibleError, ScoutError } from './errors';
import { activityScope, canAccessStudent, studentIdScope, studentScope } from './scope';
import { get, put, remove } from './storage';
import { hasRole, isActive, loadUser, type AuthUser } from './users';

export type CertificateRow = typeof schema.certificates.$inferSelect;
export type TemplateRow = typeof schema.certificateTemplates.$inferSelect;
type CertType = 'badge' | 'general' | 'leadership';

const C = schema.certificates;
const T = schema.certificateTemplates;

export const rand = (n: number) => randomBytes(n).toString('hex').slice(0, n).toUpperCase();

export const canIssue = (u: AuthUser) => isActive(u) && hasRole(u, 'leader');

export const findCertificateByUuid = async (uuid: string): Promise<CertificateRow | null> =>
  (await db.select().from(C).where(eq(C.uuid, uuid)).limit(1))[0] ?? null;

export const findCertificateByNumber = async (number: string): Promise<CertificateRow | null> => {
  const n = number.trim().toUpperCase();
  return n ? (await db.select().from(C).where(eq(C.certNumber, n)).limit(1))[0] ?? null : null;
};

export async function canViewCertificate(user: AuthUser, c: Pick<CertificateRow, 'studentId'>): Promise<boolean> {
  return isActive(user) && (await canAccessStudentById(user, c.studentId));
}

/** Leaders manage (sign, regenerate) certificates of scouts they can access. */
export async function canManageCertificate(user: AuthUser, c: Pick<CertificateRow, 'studentId'>): Promise<boolean> {
  return canIssue(user) && (await canAccessStudentById(user, c.studentId));
}

async function canAccessStudentById(user: AuthUser, studentId: number): Promise<boolean> {
  const [s] = await db.select({ id: schema.students.id, status: schema.students.status }).from(schema.students).where(eq(schema.students.id, studentId)).limit(1);
  return !!s && canAccessStudent(user, s);
}

/** Where certificate PDFs live. */
const pdfPath = (certNumber: string) => `certificates/${certNumber}.pdf`;

export async function certificatePdf(c: Pick<CertificateRow, 'path'>): Promise<Buffer | null> {
  return c.path ? get('local', c.path) : null;
}

export async function activeTemplate(type: CertType, conn: DbLike = db): Promise<TemplateRow | null> {
  return (await conn.select().from(T).where(and(eq(T.type, type), eq(T.active, true))).orderBy(asc(T.id)).limit(1))[0] ?? null;
}

function assertTemplate(template: TemplateRow | null, type: CertType) {
  if (!template) return;
  if (!template.active) throw new ScoutError('That certificate template is not active.');
  if (template.type !== type) throw new ScoutError(`That template is for ${template.type} certificates, not ${type}.`);
}

/** Everything the PDF needs, from the stored certificate. */
async function sheetFor(c: CertificateRow, conn: DbLike = db): Promise<CertificateSheet> {
  let leadership: typeof schema.leadershipRecords.$inferSelect | undefined;
  if (c.type === 'leadership') [leadership] = await conn.select().from(schema.leadershipRecords).where(eq(schema.leadershipRecords.certificateId, c.id)).limit(1);
  let verifier: CertificateSheet['verifier'] = null;
  if (c.status === 'verified' && c.verifiedBy) {
    const user = await loadUser(c.verifiedBy);
    if (user) {
      const signature = user.signaturePath ? await get('signatures', user.signaturePath) : null;
      verifier = { name: user.name, signature, dateLong: formatLongDate(c.verifiedAt) };
    }
  }
  return {
    type: c.type as CertType, name: c.studentName, idCardNo: c.idCardNo, title: c.title, badgeName: c.badgeName, certNumber: c.certNumber, dateLong: formatLongDate(c.dateAwarded),
    patrolOrSix: leadership?.patrolOrSix, troopOrGroup: leadership?.troopOrGroup, startDateLong: leadership ? formatLongDate(leadership.startDate) : null, verifier,
  };
}

interface Issue { type: CertType; student: { id: number; name: string; nationalId: string }; template: TemplateRow | null; actor: AuthUser; values: Omit<typeof C.$inferInsert, 'certId' | 'type' | 'studentId' | 'studentName' | 'idCardNo' | 'status' | 'templateId' | 'createdBy'> }

/** One pipeline for every certificate: number → row → PDF → storage. */
async function issue(input: (conn: DbLike) => Promise<Issue>): Promise<CertificateRow> {
  let stored: string | null = null;
  try {
    return await db.transaction(async (tx) => {
      const row = await issueIn(tx, input);
      stored = row.path;
      return row;
    });
  } catch (e) {
    if (stored) await remove('local', stored);
    throw e;
  }
}

type BadgeRow = typeof schema.badges.$inferSelect;
type StudentLite = { id: number; name: string; nationalId: string };

export async function generateBadgeCertificate(student: StudentLite, badge: BadgeRow, date: string, template: TemplateRow | null, actor: AuthUser, badgeRequestId: number | null = null, conn?: DbLike) {
  if (!template) {
    const linked = badge.certificateTemplateId ? (await db.select().from(T).where(eq(T.id, badge.certificateTemplateId)))[0] : null;
    template = linked?.active ? linked : await activeTemplate('badge');
  }
  assertTemplate(template, 'badge');
  const useTemplate = template;
  const build = async (tx: DbLike): Promise<Issue> => ({
    type: 'badge', student, template: useTemplate, actor,
    values: { certNumber: await nextBadgeNumber(tx, badge), dateAwarded: date, badgeId: badge.id, badgeName: badge.name, title: badge.name, badgeRequestId },
  });
  return conn ? issueIn(conn, build) : issue(build);
}

export async function generateGeneralCertificate(student: StudentLite, title: string, date: string, template: TemplateRow | null, actor: AuthUser, activity: { id: number; certificateTemplateId: number | null } | null = null) {
  if (!template && activity?.certificateTemplateId) template = (await db.select().from(T).where(eq(T.id, activity.certificateTemplateId)))[0] ?? null;
  if (!template) throw new ScoutError('Choose a general certificate template.');
  assertTemplate(template, 'general');
  if (!title.trim()) throw new ScoutError('A general certificate needs a title.');
  const t = template;
  return issue(async (tx) => ({
    type: 'general', student, template: t, actor,
    values: { certNumber: await nextGeneralNumber(tx), dateAwarded: date, title, activityId: activity?.id ?? null },
  }));
}

/** Same as `issue`, inside a transaction the caller already holds. */
async function issueIn(tx: DbLike, input: (conn: DbLike) => Promise<Issue>): Promise<CertificateRow> {
  const i = await input(tx);
  const now = new Date();
  const [ins] = await tx.insert(C).values({
    ...i.values, certId: `C${rand(8)}`, type: i.type, studentId: i.student.id, studentName: i.student.name, idCardNo: i.student.nationalId, status: 'issued',
    templateId: i.template?.id ?? null, createdBy: i.actor.id, createdAt: now, updatedAt: now,
  }).$returningId();
  const [row] = await tx.select().from(C).where(eq(C.id, ins.id));
  const path = await put('local', pdfPath(row.certNumber), await renderCertificatePdf(await sheetFor(row, tx)));
  await tx.update(C).set({ path, generatedBy: i.actor.id, generatedAt: now, updatedAt: now }).where(eq(C.id, row.id));
  await recordAudit('certificate.issued', { type: 'certificate', id: row.uuid }, { type: i.type, cert_number: row.certNumber }, i.actor.id, tx);
  return { ...row, path };
}

/** First time: a new LEAD number. Again: refresh the same certificate and number. */
export async function generateLeadershipCertificate(recordId: number, actor: AuthUser): Promise<CertificateRow> {
  const [record] = await db.select().from(schema.leadershipRecords).where(eq(schema.leadershipRecords.id, recordId));
  if (!record) throw new ScoutError('That leadership record no longer exists.');
  const [student] = await db.select().from(schema.students).where(eq(schema.students.id, record.studentId));
  const template = await activeTemplate('leadership');
  if (record.certificateId) {
    await db.update(C).set({ studentName: student.name, idCardNo: student.nationalId, dateAwarded: record.startDate, templateId: template?.id ?? null, updatedAt: new Date() }).where(eq(C.id, record.certificateId));
    const [existing] = await db.select().from(C).where(eq(C.id, record.certificateId));
    return regenerateCertificate(existing, actor);
  }
  const cert = await issue(async (tx) => ({
    type: 'leadership', student, template, actor,
    values: { certNumber: await nextLeadershipNumber(tx), dateAwarded: record.startDate, title: `Leadership — ${record.patrolOrSix}` },
  }));
  await db.update(schema.leadershipRecords).set({ certificateId: cert.id, updatedAt: new Date() }).where(eq(schema.leadershipRecords.id, record.id));
  return cert;
}

/** Re-render under the same number with the current details. */
export async function regenerateCertificate(cert: CertificateRow, actor: AuthUser): Promise<CertificateRow> {
  const [fresh] = await db.select().from(C).where(eq(C.id, cert.id));
  const path = await put('local', pdfPath(fresh.certNumber), await renderCertificatePdf(await sheetFor(fresh)));
  await db.update(C).set({ path, generatedBy: actor.id, generatedAt: new Date(), updatedAt: new Date() }).where(eq(C.id, fresh.id));
  await recordAudit('certificate.regenerated', { type: 'certificate', id: fresh.uuid }, { cert_number: fresh.certNumber }, actor.id);
  return { ...fresh, path };
}

/** Apply the signer's signature and mark the certificate verified. */
export async function signCertificate(cert: CertificateRow, actor: AuthUser): Promise<CertificateRow> {
  if (!(await canManageCertificate(actor, cert))) throw new NotAccessibleError('You cannot sign this certificate.');
  await db.update(C).set({ status: 'verified', verifiedBy: actor.id, verifiedAt: new Date(), updatedAt: new Date() }).where(eq(C.id, cert.id));
  const result = await regenerateCertificate(cert, actor);
  await recordAudit('certificate.verified_signed', { type: 'certificate', id: cert.uuid }, { cert_number: cert.certNumber }, actor.id);
  return result;
}

/** PDF bytes for a certificate, re-creating the file if it went missing. */
export async function pdfFor(cert: CertificateRow, actor: AuthUser | null): Promise<Buffer | null> {
  const existing = await certificatePdf(cert);
  if (existing) return existing;
  const pdf = await renderCertificatePdf(await sheetFor(cert));
  const path = await put('local', pdfPath(cert.certNumber), pdf);
  await db.update(C).set({ path, updatedAt: new Date() }).where(eq(C.id, cert.id));
  if (actor) await recordAudit('certificate.regenerated', { type: 'certificate', id: cert.uuid }, { cert_number: cert.certNumber }, actor.id);
  return pdf;
}

// ── Queries ────────────────────────────────────────────────────────────────

export interface CertificateFilters { q?: string; student?: string; type?: string; status?: string; badge?: string; from?: string; to?: string; sort?: string; page: number }

async function certificateWhere(user: AuthUser, f: Omit<CertificateFilters, 'page'>) {
  const term = f.q?.trim();
  const [student] = f.student ? await db.select({ id: schema.students.id }).from(schema.students).where(eq(schema.students.uuid, f.student)).limit(1) : [];
  const [badge] = f.badge ? await db.select({ id: schema.badges.id }).from(schema.badges).where(eq(schema.badges.uuid, f.badge)).limit(1) : [];
  return and(
    term ? or(like(C.certNumber, `%${term}%`), like(C.studentName, `%${term}%`), like(C.title, `%${term}%`)) : undefined,
    f.student ? eq(C.studentId, student?.id ?? 0) : undefined,
    f.type ? eq(C.type, f.type) : undefined,
    f.status ? eq(C.status, f.status) : undefined,
    f.badge ? eq(C.badgeId, badge?.id ?? 0) : undefined,
    f.from ? gte(C.dateAwarded, f.from) : undefined,
    f.to ? lte(C.dateAwarded, f.to) : undefined,
    await studentIdScope(user, C.studentId),
  );
}

export async function listCertificates(user: AuthUser, f: CertificateFilters) {
  const where = await certificateWhere(user, f);
  const [{ n }] = await db.select({ n: count() }).from(C).where(where);
  const order = f.sort === 'oldest' ? [asc(C.dateAwarded), asc(C.id)] : [desc(C.dateAwarded), desc(C.id)];
  const rows = await db.select().from(C).where(where).orderBy(...order).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), f.page);
}

export async function certificatesByUuids(user: AuthUser, uuids: string[]) {
  return db.select().from(C).where(and(inArray(C.uuid, uuids), await studentIdScope(user, C.studentId)));
}

export async function certificateStats(user: AuthUser) {
  const scope = await studentIdScope(user, C.studentId);
  const requestScope = await studentIdScope(user, schema.badgeRequests.studentId);
  const year = todayLocal().slice(0, 4);
  const rows = await db.select({ type: C.type, awarded: C.dateAwarded, generatedAt: C.generatedAt }).from(C).where(scope);
  const requests = await db.select({ status: schema.badgeRequests.status }).from(schema.badgeRequests).where(requestScope);
  const dayStart = startOfToday();
  return {
    total: rows.length,
    thisYear: rows.filter((r) => r.awarded.startsWith(year)).length,
    badge: rows.filter((r) => r.type === 'badge').length,
    general: rows.filter((r) => r.type === 'general').length,
    leadership: rows.filter((r) => r.type === 'leadership').length,
    requestsPending: requests.filter((r) => r.status === 'requested').length,
    requestsApproved: requests.filter((r) => r.status === 'approved').length,
    requestsRejected: requests.filter((r) => r.status === 'rejected').length,
    generatedToday: rows.filter((r) => r.generatedAt && r.generatedAt >= dayStart).length,
  };
}

// ── Badge requests ─────────────────────────────────────────────────────────

const R = schema.badgeRequests;
export type BadgeRequestRow = typeof R.$inferSelect;

export const findRequestByUuid = async (uuid: string): Promise<BadgeRequestRow | null> => (await db.select().from(R).where(eq(R.uuid, uuid)).limit(1))[0] ?? null;

export async function canActOnRequest(user: AuthUser, r: Pick<BadgeRequestRow, 'studentId'>) {
  return isActive(user) && (await canAccessStudentById(user, r.studentId));
}
export async function canDecideRequest(user: AuthUser, r: Pick<BadgeRequestRow, 'studentId'>) {
  return isActive(user) && hasRole(user, 'leader') && (await canAccessStudentById(user, r.studentId));
}

export async function requestBadge(student: { id: number; name: string; status: string }, badge: BadgeRow, actor: AuthUser): Promise<BadgeRequestRow> {
  if (!(await canAccessStudent(actor, student))) throw new NotAccessibleError('You cannot request a badge for this scout.');
  return db.transaction(async (tx) => {
    const open = await tx.select({ id: R.id }).from(R).where(and(eq(R.studentId, student.id), eq(R.badgeId, badge.id), inArray(R.status, ['requested', 'approved', 'generated']))).for('update');
    if (open.length) throw new ScoutError(`${student.name} already has a request for the ${badge.name} badge.`);
    const now = new Date();
    const [ins] = await tx.insert(R).values({
      requestId: `BR-${rand(6)}`, studentId: student.id, studentName: student.name, badgeId: badge.id, badgeName: badge.name, status: 'requested', requestedBy: actor.id, createdAt: now, updatedAt: now,
    }).$returningId();
    const [row] = await tx.select().from(R).where(eq(R.id, ins.id));
    await recordAudit('badge_request.created', { type: 'badge_request', id: row.uuid }, { badge: badge.code }, actor.id, tx);
    return row;
  });
}

async function decide(request: BadgeRequestRow, actor: AuthUser, status: 'approved' | 'rejected', note: string | null) {
  await db.transaction(async (tx) => {
    const [locked] = await tx.select().from(R).where(eq(R.id, request.id)).for('update');
    if (!locked || locked.status !== 'requested') throw new ScoutError('Only requested badges can be approved or rejected.');
    await tx.update(R).set({ status, reviewedBy: actor.id, reviewedAt: new Date(), reviewNote: note, updatedAt: new Date() }).where(eq(R.id, locked.id));
    await recordAudit(`badge_request.${status}`, { type: 'badge_request', id: locked.uuid }, { note }, actor.id, tx);
  });
}
export const approveRequest = (r: BadgeRequestRow, actor: AuthUser, note: string | null = null) => decide(r, actor, 'approved', note);
export const rejectRequest = (r: BadgeRequestRow, actor: AuthUser, note: string | null = null) => decide(r, actor, 'rejected', note);

export async function generateApproved(request: BadgeRequestRow, actor: AuthUser, date: string, template: TemplateRow | null = null): Promise<CertificateRow> {
  let stored: string | null = null;
  try {
    return await db.transaction(async (tx) => {
      const [locked] = await tx.select().from(R).where(eq(R.id, request.id)).for('update');
      if (!locked || locked.status !== 'approved') throw new ScoutError('Only approved badge requests can be generated.');
      const [student] = await tx.select().from(schema.students).where(eq(schema.students.id, locked.studentId));
      const [badge] = await tx.select().from(schema.badges).where(eq(schema.badges.id, locked.badgeId));
      const cert = await generateBadgeCertificate(student, badge, date, template, actor, locked.id, tx);
      stored = cert.path;
      await tx.update(R).set({
        status: 'generated', certificateNumber: cert.certNumber, dateAwarded: cert.dateAwarded, certificatePath: cert.path, generatedBy: actor.id, generatedAt: new Date(), updatedAt: new Date(),
      }).where(eq(R.id, locked.id));
      await recordAudit('badge_request.generated', { type: 'badge_request', id: locked.uuid }, { cert_number: cert.certNumber }, actor.id, tx);
      return cert;
    });
  } catch (e) {
    if (stored) await remove('local', stored);
    throw e;
  }
}

export async function listRequests(user: AuthUser, f: { status?: string; q?: string; studentId?: number; page: number }) {
  const term = f.q?.trim();
  const where = and(
    f.status ? eq(R.status, f.status) : undefined,
    f.studentId ? eq(R.studentId, f.studentId) : undefined,
    term ? or(like(R.studentName, `%${term}%`), like(R.badgeName, `%${term}%`), like(R.requestId, `%${term}%`)) : undefined,
    await studentIdScope(user, R.studentId),
  );
  const [{ n }] = await db.select({ n: count() }).from(R).where(where);
  const rows = await db.select().from(R).where(where).orderBy(desc(R.createdAt), desc(R.id)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), f.page);
}

// ── Bulk and activity certificates ─────────────────────────────────────────

/** Each scout is its own transaction; failures never roll back successes. */
export async function bulkCreateGeneral(studentIds: number[], title: string, date: string, template: TemplateRow, actor: AuthUser) {
  const created: CertificateRow[] = [];
  const failed: Record<string, string> = {};
  const students = studentIds.length ? await db.select().from(schema.students).where(inArray(schema.students.id, studentIds)).orderBy(asc(schema.students.name)) : [];
  for (const student of students) {
    try {
      if (!(await canAccessStudent(actor, student))) throw new NotAccessibleError('Not one of your scouts.');
      created.push(await generateGeneralCertificate(student, title, date, template, actor));
    } catch (e) {
      failed[student.name] = e instanceof Error ? e.message : 'Failed';
    }
  }
  return { created, failed };
}

/** Present or late scouts of the activity who have no certificate for it yet. */
async function missingStudents(activityId: number) {
  const attended = (await db.select({ id: schema.attendanceRecords.studentId }).from(schema.attendanceRecords)
    .where(and(eq(schema.attendanceRecords.activityId, activityId), inArray(schema.attendanceRecords.status, ['Present', 'Late'])))).map((r) => r.id);
  if (!attended.length) return [];
  const have = (await db.select({ id: C.studentId }).from(C).where(and(eq(C.activityId, activityId), eq(C.type, 'general')))).map((r) => r.id);
  const missing = attended.filter((id) => !have.includes(id));
  return missing.length ? db.select().from(schema.students).where(inArray(schema.students.id, missing)).orderBy(asc(schema.students.name)) : [];
}

export async function issueForActivityAttendance(activity: typeof schema.activities.$inferSelect, actor: AuthUser) {
  if (activity.certificateTemplateId === null) return { issued: 0, failed: {} as Record<string, string> };
  let issued = 0;
  const failed: Record<string, string> = {};
  for (const student of await missingStudents(activity.id)) {
    try {
      await generateGeneralCertificate(student, activity.name, activity.date, null, actor, activity);
      issued++;
    } catch (e) {
      failed[student.name] = e instanceof Error ? e.message : 'Failed';
    }
  }
  return { issued, failed };
}

export async function activityCertificateSummaries(user: AuthUser) {
  const A = schema.activities;
  const activities = await db.select().from(A).where(and(isNull(A.deletedAt), isNotNull(A.certificateTemplateId), await activityScope(user))).orderBy(desc(A.date)).limit(50);
  const out = [];
  for (const activity of activities) {
    const [{ present }] = await db.select({ present: count() }).from(schema.attendanceRecords).where(and(eq(schema.attendanceRecords.activityId, activity.id), inArray(schema.attendanceRecords.status, ['Present', 'Late'])));
    const [{ issued }] = await db.select({ issued: count() }).from(C).where(and(eq(C.activityId, activity.id), eq(C.type, 'general')));
    out.push({ activity, present: Number(present), issued: Number(issued), missing: (await missingStudents(activity.id)).length });
  }
  return out;
}


/** Active scouts this person can issue or request for (uuid, name, section). */
export async function accessibleStudents(user: AuthUser, section?: string | null) {
  const S = schema.students;
  const rows = await db.select({ id: S.id, uuid: S.uuid, name: S.name, section: S.section, status: S.status }).from(S)
    .where(and(isNull(S.deletedAt), eq(S.status, 'active'), section ? eq(S.section, section) : undefined, await studentScope(user))).orderBy(asc(S.name));
  const out = [];
  for (const s of rows) if (await canAccessStudent(user, s)) out.push(s);
  return out;
}
