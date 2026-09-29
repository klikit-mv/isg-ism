import { eq } from 'drizzle-orm';
import { db, schema, type DbLike } from '@/db';
import { recordAudit } from './audit';
import { ScoutError } from './errors';
import type { AuthUser } from './users';

/** Stock falls exactly once, when a purchase becomes fully paid. */
export async function assertPurchasable(item: { name: string; status: string; stockQty: number }, quantity: number): Promise<void> {
  if (item.status !== 'Active') throw new ScoutError(`${item.name} is not available.`);
  if (quantity < 1) throw new ScoutError('Choose a quantity of at least 1.');
  if (item.stockQty < quantity) throw new ScoutError(`Only ${item.stockQty} of ${item.name} left in stock.`);
}

async function decrementItem(shopItemId: number, quantity: number, purchase: { id: number; uuid: string }, actorId: number | null, conn: DbLike) {
  const [item] = await conn.select().from(schema.shopItems).where(eq(schema.shopItems.id, shopItemId)).for('update');
  if (!item || item.stockQty < quantity) {
    throw new ScoutError(`Not enough stock of ${item?.name} (${item?.stockQty} left, ${quantity} needed). Reject this payment and refund the buyer, or restock first.`);
  }
  await conn.update(schema.shopItems).set({ stockQty: item.stockQty - quantity, updatedAt: new Date() }).where(eq(schema.shopItems.id, item.id));
  await conn.insert(schema.stockMovements).values({
    shopItemId: item.id, type: 'decrement', quantity, referenceType: 'purchase', referenceId: purchase.id, actorUserId: actorId, createdAt: new Date(), updatedAt: new Date(),
  });
  await recordAudit('stock.decremented', { type: 'shop_item', id: item.uuid }, { quantity, purchase: purchase.uuid }, actorId, conn);
}

/**
 * Run inside the caller's transaction once a purchase may have become fully paid.
 * Returns true when stock was taken.
 */
export async function decrementForPurchase(purchaseId: number, actor: AuthUser | null, conn: DbLike = db): Promise<boolean> {
  const [purchase] = await conn.select().from(schema.purchases).where(eq(schema.purchases.id, purchaseId)).for('update');
  if (!purchase || purchase.stockDecremented || purchase.paymentStatus !== 'Paid') return false;
  const lines = await conn.select().from(schema.purchaseItems).where(eq(schema.purchaseItems.purchaseId, purchase.id));
  for (const line of lines) await decrementItem(line.shopItemId, line.quantity, purchase, actor?.id ?? null, conn);
  await conn.update(schema.purchases).set({ stockDecremented: true, updatedAt: new Date() }).where(eq(schema.purchases.id, purchase.id));
  return true;
}
