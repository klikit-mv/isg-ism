import { and, count, desc, eq, exists, isNull, like, or } from 'drizzle-orm';
import { db, schema } from '@/db';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import * as money from '@/lib/money';
import { recordAudit } from './audit';
import { NotAccessibleError, ScoutError } from './errors';
import { assertPurchasable } from './inventory';
import { canAccessStudent, studentIdScope } from './scope';
import { shopEnabled } from './settings';
import { hasPermission, isActive, type AuthUser } from './users';

export const canProcessDelivery = (u: AuthUser) => isActive(u) && hasPermission(u, 'canProcessDelivery');

export async function createPurchase(item: typeof schema.shopItems.$inferSelect, student: { id: number; status: string }, quantity: number, actor: AuthUser) {
  if (!(await shopEnabled())) throw new ScoutError('The shop is closed at the moment.');
  if (!(await canAccessStudent(actor, student))) throw new NotAccessibleError('You cannot buy for this scout.');
  const now = new Date();
  return db.transaction(async (tx) => {
    const [locked] = await tx.select().from(schema.shopItems).where(and(eq(schema.shopItems.id, item.id), isNull(schema.shopItems.deletedAt))).for('update');
    if (!locked) throw new ScoutError('That item is no longer sold.');
    await assertPurchasable(locked, quantity);
    const total = money.mul(locked.price, quantity);
    const [ins] = await tx.insert(schema.purchases).values({
      studentId: student.id, createdBy: actor.id, totalAmount: total, paidAmount: '0.00', outstandingAmount: total,
      paymentStatus: 'Pending', purchaseStatus: 'PendingPayment', createdAt: now, updatedAt: now,
    }).$returningId();
    await tx.insert(schema.purchaseItems).values({ purchaseId: ins.id, shopItemId: locked.id, itemNameSnapshot: locked.name, quantity, unitPrice: locked.price, totalAmount: total, createdAt: now, updatedAt: now });
    await recordAudit('purchase.created', { type: 'purchase', id: ins.id }, { item: locked.name, quantity, total }, actor.id, tx);
    return (await tx.select().from(schema.purchases).where(eq(schema.purchases.id, ins.id)))[0];
  });
}

function assertDeliveryStaff(actor: AuthUser) {
  if (!canProcessDelivery(actor)) throw new ScoutError('Only admins or users with the Process delivery permission can do this.');
}

export async function markReady(purchaseId: number, actor: AuthUser): Promise<void> {
  assertDeliveryStaff(actor);
  await db.transaction(async (tx) => {
    const [p] = await tx.select().from(schema.purchases).where(eq(schema.purchases.id, purchaseId)).for('update');
    if (!p || p.paymentStatus !== 'Paid' || p.purchaseStatus !== 'Confirmed') throw new ScoutError('Only fully paid, confirmed purchases can be marked ready for collection.');
    await tx.update(schema.purchases).set({ purchaseStatus: 'ReadyForCollection', updatedAt: new Date() }).where(eq(schema.purchases.id, p.id));
    await recordAudit('purchase.ready', { type: 'purchase', id: p.id }, {}, actor.id, tx);
  });
}

export async function deliverPurchase(purchaseId: number, actor: AuthUser, recipient?: string | null): Promise<void> {
  assertDeliveryStaff(actor);
  await db.transaction(async (tx) => {
    const [p] = await tx.select().from(schema.purchases).where(eq(schema.purchases.id, purchaseId)).for('update');
    if (!p || p.paymentStatus !== 'Paid' || !['Confirmed', 'ReadyForCollection'].includes(p.purchaseStatus)) throw new ScoutError('Unpaid purchases cannot be delivered.');
    const [student] = await tx.select({ name: schema.students.name }).from(schema.students).where(eq(schema.students.id, p.studentId));
    const to = recipient?.trim() || student?.name || null;
    await tx.update(schema.purchases).set({ purchaseStatus: 'Delivered', recipient: to, deliveredBy: actor.id, deliveredAt: new Date(), updatedAt: new Date() }).where(eq(schema.purchases.id, p.id));
    await recordAudit('purchase.delivered', { type: 'purchase', id: p.id }, { recipient: to }, actor.id, tx);
  });
}

/** The buyer or shop staff may cancel until any money is approved. */
export async function cancelPurchase(purchaseId: number, actor: AuthUser): Promise<void> {
  await db.transaction(async (tx) => {
    const [p] = await tx.select().from(schema.purchases).where(eq(schema.purchases.id, purchaseId)).for('update');
    if (!p || p.purchaseStatus === 'Cancelled') return;
    if (money.isPositive(p.paidAmount) || ['Confirmed', 'ReadyForCollection', 'Delivered'].includes(p.purchaseStatus) || p.paymentStatus === 'AwaitingVerification') {
      throw new ScoutError('This purchase has a payment and cannot be cancelled.');
    }
    await tx.update(schema.purchases).set({ purchaseStatus: 'Cancelled', updatedAt: new Date() }).where(eq(schema.purchases.id, p.id));
    await recordAudit('purchase.cancelled', { type: 'purchase', id: p.id }, {}, actor.id, tx);
  });
}

export const findPurchaseByUuid = async (uuid: string) => (await db.select().from(schema.purchases).where(eq(schema.purchases.uuid, uuid)).limit(1))[0] ?? null;

/** May this user act for the purchase (shop staff or anyone who can act for the scout)? */
export async function canActOnPurchase(user: AuthUser, purchase: { studentId: number }): Promise<boolean> {
  if (!isActive(user)) return false;
  if (hasPermission(user, 'canManageShop')) return true;
  const [s] = await db.select({ id: schema.students.id, status: schema.students.status }).from(schema.students).where(eq(schema.students.id, purchase.studentId));
  return !!s && canAccessStudent(user, s);
}

export async function listPurchases(user: AuthUser, f: { q?: string; payment_status?: string; purchase_status?: string; page: number }) {
  const P = schema.purchases;
  const S = schema.students;
  const staff = hasPermission(user, 'canManageShop') || hasPermission(user, 'canProcessDelivery');
  const term = f.q?.trim();
  const where = and(
    term ? or(like(S.name, `%${term}%`), exists(db.select({ x: schema.purchaseItems.id }).from(schema.purchaseItems).where(and(eq(schema.purchaseItems.purchaseId, P.id), like(schema.purchaseItems.itemNameSnapshot, `%${term}%`))))) : undefined,
    f.payment_status ? eq(P.paymentStatus, f.payment_status) : undefined,
    f.purchase_status ? eq(P.purchaseStatus, f.purchase_status) : undefined,
    staff ? undefined : await studentIdScope(user, P.studentId),
  );
  const [{ n }] = await db.select({ n: count() }).from(P).innerJoin(S, eq(S.id, P.studentId)).where(where);
  const rows = await db.select({ purchase: P, student: S.name }).from(P).innerJoin(S, eq(S.id, P.studentId)).where(where)
    .orderBy(desc(P.createdAt), desc(P.id)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);
  const ids = rows.map((r) => r.purchase.id);
  const lines = ids.length ? await db.select().from(schema.purchaseItems).where(or(...ids.map((id) => eq(schema.purchaseItems.purchaseId, id)))) : [];
  return pageOf(rows.map((r) => ({ ...r, lines: lines.filter((l) => l.purchaseId === r.purchase.id) })), Number(n), f.page);
}
