import { randomBytes } from 'node:crypto';
import { and, asc, count, eq, like, or } from 'drizzle-orm';
import { db, schema } from '@/db';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import { recordAudit } from './audit';
import { badgeSequence, setNextSequence } from './certificate-numbers';
import { ScoutError } from './errors';
import { remove } from './storage';
import { storeImage } from './uploads';
import type { AuthUser } from './users';

export type BadgeRow = typeof schema.badges.$inferSelect;
const B = schema.badges;

export const findBadgeByUuid = async (uuid: string): Promise<BadgeRow | null> => (await db.select().from(B).where(eq(B.uuid, uuid)).limit(1))[0] ?? null;

export async function listBadges(f: { q?: string; section?: string; page: number }) {
  const term = f.q?.trim();
  const where = and(term ? or(like(B.name, `%${term}%`), like(B.code, `%${term}%`)) : undefined, f.section ? eq(B.section, f.section) : undefined);
  const [{ n }] = await db.select({ n: count() }).from(B).where(where);
  const rows = await db.select().from(B).where(where).orderBy(asc(B.name)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), f.page);
}

export const allBadges = () => db.select().from(B).orderBy(asc(B.name));

export interface BadgeInput {
  name: string;
  code: string;
  section?: string | null;
  description?: string | null;
  category?: string | null;
  certificate_template_id?: number | null;
  number_prefix?: string | null;
  next_number?: number | null;
}

const attributes = (d: BadgeInput) => ({
  name: d.name,
  code: d.code.trim().toUpperCase(),
  section: d.section || null,
  description: d.description ?? null,
  category: d.category?.trim() ? d.category.trim().toLowerCase() : 'proficiency',
  certificateTemplateId: d.certificate_template_id ?? null,
  numberPrefix: d.number_prefix?.trim() ? d.number_prefix.trim().toUpperCase() : null,
});

async function uniqueCode(code: string, exceptId?: number) {
  const [dupe] = await db.select({ id: B.id }).from(B).where(eq(B.code, code.trim().toUpperCase())).limit(1);
  if (dupe && dupe.id !== exceptId) throw new ScoutError('That badge code is already used.', 'code');
}

async function applyNextNumber(badge: BadgeRow, d: BadgeInput) {
  if (!d.next_number) return;
  await setNextSequence(badgeSequence(badge).counter, d.next_number, badge.id);
}

const setImage = async (badge: BadgeRow, image: File) => {
  const path = await storeImage(image, { directory: 'badges', name: `${badge.uuid}-${Date.now().toString(36)}`, field: 'image', maxKb: 5120 });
  await db.update(B).set({ imagePath: path, updatedAt: new Date() }).where(eq(B.id, badge.id));
  await remove('public', badge.imagePath);
};

export async function createBadge(data: BadgeInput, image: File | null, actor: AuthUser): Promise<BadgeRow> {
  await uniqueCode(data.code);
  const now = new Date();
  const [ins] = await db.insert(B).values({ ...attributes(data), badgeId: `B${randomBytes(3).toString('hex').slice(0, 5).toUpperCase()}`, createdAt: now, updatedAt: now }).$returningId();
  let [badge] = await db.select().from(B).where(eq(B.id, ins.id));
  if (image) await setImage(badge, image);
  [badge] = await db.select().from(B).where(eq(B.id, ins.id));
  await applyNextNumber(badge, data);
  await recordAudit('badge.created', { type: 'badge', id: badge.uuid }, { name: badge.name, code: badge.code }, actor.id);
  return badge;
}

export async function updateBadge(badge: BadgeRow, data: BadgeInput, image: File | null, actor: AuthUser): Promise<void> {
  await uniqueCode(data.code, badge.id);
  await db.update(B).set({ ...attributes(data), updatedAt: new Date() }).where(eq(B.id, badge.id));
  if (image) await setImage(badge, image);
  const [fresh] = await db.select().from(B).where(eq(B.id, badge.id));
  await applyNextNumber(fresh, data);
  await recordAudit('badge.updated', { type: 'badge', id: badge.uuid }, { name: data.name }, actor.id);
}

export async function deleteBadge(badge: BadgeRow, actor: AuthUser): Promise<void> {
  const [{ a }] = await db.select({ a: count() }).from(schema.certificates).where(eq(schema.certificates.badgeId, badge.id));
  const [{ b }] = await db.select({ b: count() }).from(schema.badgeRequests).where(eq(schema.badgeRequests.badgeId, badge.id));
  if (Number(a) + Number(b) > 0) throw new ScoutError('This badge has requests or certificates and cannot be deleted.');
  await recordAudit('badge.deleted', { type: 'badge', id: badge.uuid }, { name: badge.name }, actor.id);
  await remove('public', badge.imagePath);
  await db.delete(B).where(eq(B.id, badge.id));
}
