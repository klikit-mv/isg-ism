import { and, asc, count, eq, isNull, like, or } from 'drizzle-orm';
import { db, schema } from '@/db';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import { recordAudit } from './audit';
import { remove } from './storage';
import { studentScope, canAccessStudent } from './scope';
import { storeImage } from './uploads';
import { config } from '@/lib/config';
import { hasPermission, type AuthUser } from './users';

export type ShopItemRow = typeof schema.shopItems.$inferSelect;

export const canManageShop = (u: AuthUser) => hasPermission(u, 'canManageShop');

export const findItemByUuid = async (uuid: string): Promise<ShopItemRow | null> =>
  (await db.select().from(schema.shopItems).where(and(eq(schema.shopItems.uuid, uuid), isNull(schema.shopItems.deletedAt))).limit(1))[0] ?? null;

export async function listItems(user: AuthUser, f: { q?: string; status?: string; page: number }) {
  const I = schema.shopItems;
  const manager = canManageShop(user);
  const term = f.q?.trim();
  const where = and(
    isNull(I.deletedAt),
    manager ? (f.status ? eq(I.status, f.status) : undefined) : eq(I.status, 'Active'),
    term ? or(like(I.name, `%${term}%`), like(I.description, `%${term}%`)) : undefined,
  );
  const [{ n }] = await db.select({ n: count() }).from(I).where(where);
  const rows = await db.select().from(I).where(where).orderBy(asc(I.name)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), f.page);
}

/** Active scouts this person may buy for. */
export async function buyableStudents(user: AuthUser) {
  const S = schema.students;
  const rows = await db.select({ id: S.id, uuid: S.uuid, name: S.name, status: S.status }).from(S).where(and(isNull(S.deletedAt), eq(S.status, 'active'), await studentScope(user))).orderBy(asc(S.name));
  const out = [];
  for (const s of rows) if (await canAccessStudent(user, s)) out.push(s);
  return out;
}

export interface ItemInput { name: string; description?: string | null; price: string; stock_qty: number; status?: string }

export async function createItem(data: ItemInput, image: File | null, actor: AuthUser): Promise<ShopItemRow> {
  const now = new Date();
  const [ins] = await db.insert(schema.shopItems).values({
    name: data.name, description: data.description ?? null, price: data.price, stockQty: data.stock_qty, status: data.status ?? 'Active', createdBy: actor.id, createdAt: now, updatedAt: now,
  }).$returningId();
  const [item] = await db.select().from(schema.shopItems).where(eq(schema.shopItems.id, ins.id));
  if (image) await setImage(item, image);
  await recordAudit('shop_item.created', { type: 'shop_item', id: item.uuid }, { name: item.name, price: item.price, stock: item.stockQty }, actor.id);
  return item;
}

export async function updateItem(item: ShopItemRow, data: ItemInput, image: File | null, actor: AuthUser): Promise<void> {
  await db.update(schema.shopItems).set({
    name: data.name, description: data.description ?? null, price: data.price, stockQty: data.stock_qty, ...(data.status ? { status: data.status } : {}), updatedAt: new Date(),
  }).where(eq(schema.shopItems.id, item.id));
  if (image) await setImage(item, image);
  await recordAudit('shop_item.updated', { type: 'shop_item', id: item.uuid }, { name: data.name, price: data.price, stock: data.stock_qty }, actor.id);
}

async function setImage(item: ShopItemRow, file: File) {
  const path = await storeImage(file, { directory: 'shop-items', name: `${item.uuid}-${Date.now().toString(36)}`, field: 'image', maxKb: config.shopImageMaxKb });
  await db.update(schema.shopItems).set({ imagePath: path, updatedAt: new Date() }).where(eq(schema.shopItems.id, item.id));
  await remove('public', item.imagePath);
}

export async function deleteItem(item: ShopItemRow, actor: AuthUser): Promise<void> {
  await recordAudit('shop_item.deleted', { type: 'shop_item', id: item.uuid }, { name: item.name }, actor.id);
  await db.update(schema.shopItems).set({ deletedAt: new Date() }).where(eq(schema.shopItems.id, item.id));
}
