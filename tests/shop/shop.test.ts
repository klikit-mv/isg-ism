import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { loadPayableOrThrow } from '@/server/payables';
import { approvePayment, submitPayment } from '@/server/payments';
import { cancelPurchase, createPurchase, deliverPurchase, markReady } from '@/server/purchases';
import { createItem } from '@/server/shop';
import { setSetting } from '@/server/settings';
import { makeAdmin, makeParentOf, makeStudent, makeUser } from '../factories';

const item = async (stock = 5, price = '25.00') => createItem({ name: 'Scarf', price, stock_qty: stock }, null, await makeAdmin());
const png = () => {
  const b = Buffer.alloc(40);
  Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]).copy(b);
  return new File([new Uint8Array(b)], 'proof.png', { type: 'image/png' });
};
const stockOf = async (id: number) => (await db.select().from(schema.shopItems).where(eq(schema.shopItems.id, id)))[0].stockQty;

describe('shop purchases', () => {
  it('a parent orders for their child; stock falls once, when fully paid', async () => {
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const treasurer = await makeUser({ roles: ['leader'], permissions: ['canVerifyPayments', 'canProcessDelivery', 'canManageShop'] });
    const scarf = await item(5, '25.00');

    const purchase = await createPurchase(scarf, scout, 2, parent);
    expect(purchase.totalAmount).toBe('50.00');
    expect(await stockOf(scarf.id)).toBe(5);

    const payable = await loadPayableOrThrow('purchase', { id: purchase.id });
    const payment = await submitPayment(payable, parent, '50.00', 'online', png());
    expect(await stockOf(scarf.id)).toBe(5);
    await approvePayment(payment.id, treasurer);
    expect(await stockOf(scarf.id)).toBe(3);

    await markReady(purchase.id, treasurer);
    await deliverPurchase(purchase.id, treasurer, 'Parent');
    const [done] = await db.select().from(schema.purchases).where(eq(schema.purchases.id, purchase.id));
    expect(done.purchaseStatus).toBe('Delivered');
    expect(done.recipient).toBe('Parent');
    expect(await stockOf(scarf.id)).toBe(3);
  });

  it('cannot order more than is in stock, or for a scout you do not look after', async () => {
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const stranger = await makeParentOf(await makeStudent());
    const scarf = await item(1);
    await expect(createPurchase(scarf, scout, 2, parent)).rejects.toThrow('Only 1 of Scarf left in stock.');
    await expect(createPurchase(scarf, scout, 1, stranger)).rejects.toThrow('cannot buy for this scout');
  });

  it('the shop can be closed', async () => {
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const scarf = await item();
    await setSetting('shop_enabled', '0');
    await expect(createPurchase(scarf, scout, 1, parent)).rejects.toThrow('The shop is closed');
  });

  it('an unpaid purchase can be cancelled, a paid one cannot', async () => {
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const admin = await makeAdmin();
    const scarf = await item();
    const a = await createPurchase(scarf, scout, 1, parent);
    await cancelPurchase(a.id, parent);
    expect((await db.select().from(schema.purchases).where(eq(schema.purchases.id, a.id)))[0].purchaseStatus).toBe('Cancelled');

    const b = await createPurchase(scarf, scout, 1, parent);
    await submitPayment(await loadPayableOrThrow('purchase', { id: b.id }), admin, '25.00', 'cash');
    await expect(cancelPurchase(b.id, parent)).rejects.toBeTruthy();
  });
});
