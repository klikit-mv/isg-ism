import { and, asc, count, eq, ne } from 'drizzle-orm';
import { db, schema } from '@/db';
import { recordAudit } from './audit';
import { ScoutError } from './errors';
import { rand, type TemplateRow } from './certificates';
import type { AuthUser } from './users';

const T = schema.certificateTemplates;

export const findTemplateByUuid = async (uuid: string): Promise<TemplateRow | null> => (await db.select().from(T).where(eq(T.uuid, uuid)).limit(1))[0] ?? null;

export async function listTemplates() {
  const templates = await db.select().from(T).orderBy(asc(T.type), asc(T.name));
  const out = [];
  for (const t of templates) {
    const [{ n }] = await db.select({ n: count() }).from(schema.certificates).where(eq(schema.certificates.templateId, t.id));
    const activity = t.activityId ? (await db.select({ name: schema.activities.name }).from(schema.activities).where(eq(schema.activities.id, t.activityId)))[0]?.name ?? null : null;
    out.push({ template: t, used: Number(n), activity });
  }
  return out;
}

export const templatesOfType = (type: string) => db.select().from(T).where(and(eq(T.type, type), eq(T.active, true))).orderBy(asc(T.name));

export interface TemplateInput { name: string; type: string; activity_id?: number | null; active?: boolean }

/** Linking a template to an activity also sets the activity's template and unlinks any other activity. */
async function linkActivity(template: TemplateRow, activityId: number | null) {
  const A = schema.activities;
  if (template.activityId && template.activityId !== activityId) {
    await db.update(A).set({ certificateTemplateId: null }).where(and(eq(A.id, template.activityId), eq(A.certificateTemplateId, template.id)));
  }
  await db.update(T).set({ activityId }).where(eq(T.id, template.id));
  if (activityId !== null) {
    await db.update(T).set({ activityId: null }).where(and(eq(T.activityId, activityId), ne(T.id, template.id)));
    await db.update(A).set({ certificateTemplateId: template.id }).where(eq(A.id, activityId));
  }
}

export async function createTemplate(data: TemplateInput, actor: AuthUser): Promise<TemplateRow> {
  const now = new Date();
  const [ins] = await db.insert(T).values({ templateId: `TPL-${rand(6)}`, name: data.name, type: data.type, googleSlideId: `local-${data.type}`, active: data.active ?? true, createdAt: now, updatedAt: now }).$returningId();
  const [row] = await db.select().from(T).where(eq(T.id, ins.id));
  await linkActivity(row, data.activity_id ?? null);
  await recordAudit('certificate_template.created', { type: 'certificate_template', id: row.uuid }, { name: row.name, type: row.type }, actor.id);
  return row;
}

export async function updateTemplate(template: TemplateRow, data: TemplateInput, actor: AuthUser): Promise<void> {
  await db.update(T).set({ name: data.name, type: data.type, active: data.active ?? template.active, updatedAt: new Date() }).where(eq(T.id, template.id));
  await linkActivity(template, data.activity_id ?? null);
  await recordAudit('certificate_template.updated', { type: 'certificate_template', id: template.uuid }, { name: data.name }, actor.id);
}

export async function setTemplateActive(template: TemplateRow, active: boolean, actor: AuthUser): Promise<void> {
  await db.update(T).set({ active, updatedAt: new Date() }).where(eq(T.id, template.id));
  await recordAudit(`certificate_template.${active ? 'activated' : 'deactivated'}`, { type: 'certificate_template', id: template.uuid }, {}, actor.id);
}

export async function deleteTemplate(template: TemplateRow, actor: AuthUser): Promise<void> {
  const [{ n }] = await db.select({ n: count() }).from(schema.certificates).where(eq(schema.certificates.templateId, template.id));
  if (Number(n) > 0) throw new ScoutError('This template has been used for certificates and cannot be deleted. Deactivate it instead.');
  await db.transaction(async (tx) => {
    await tx.update(schema.activities).set({ certificateTemplateId: null }).where(eq(schema.activities.certificateTemplateId, template.id));
    await tx.update(schema.badges).set({ certificateTemplateId: null }).where(eq(schema.badges.certificateTemplateId, template.id));
    await recordAudit('certificate_template.deleted', { type: 'certificate_template', id: template.uuid }, { name: template.name }, actor.id, tx);
    await tx.delete(T).where(eq(T.id, template.id));
  });
}

/** One active template per type, so certificates can be issued straight away. */
export async function ensureDefaultTemplates(actor: AuthUser | null = null): Promise<void> {
  const names = { badge: 'Badge certificate', general: 'General certificate', leadership: 'Leadership certificate' } as const;
  for (const type of ['badge', 'general', 'leadership'] as const) {
    const [existing] = await db.select({ id: T.id }).from(T).where(eq(T.type, type)).limit(1);
    if (existing) continue;
    const now = new Date();
    await db.insert(T).values({ templateId: `TPL-${rand(6)}`, name: names[type], type, googleSlideId: `local-${type}`, active: true, createdAt: now, updatedAt: now });
    await recordAudit('certificate_template.created', null, { name: names[type], type }, actor?.id ?? null);
  }
}
