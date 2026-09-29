import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { ScoutError, NotAccessibleError } from '@/server/errors';
import { list, unreadCount } from '@/server/notifications';
import { loadPayableOrThrow } from '@/server/payables';
import { approvePayment, canPay, rejectPayment, submitPayment } from '@/server/payments';
import { setSetting } from '@/server/settings';
import { get } from '@/server/storage';
import { makeActivity, makeAdmin, makeGroup, makeLeader, makeParentOf, makeStudent, makeUser } from '../factories';

const png = (name = 'proof.png') => {
  const b = Buffer.alloc(40);
  Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]).copy(b);
  return new File([new Uint8Array(b)], name, { type: 'image/png' });
};

async function classFee(studentId: number, amount = '50.00') {
  const activity = await makeActivity({ all: true, charge: true });
  const now = new Date();
  const [row] = await db.insert(schema.classFees).values({ activityId: activity.id, studentId, amount, outstandingAmount: amount, status: 'Pending', createdAt: now, updatedAt: now }).$returningId();
  return loadPayableOrThrow('class_fee', { id: row.id });
}
const reload = (id: number) => loadPayableOrThrow('class_fee', { id });
const treasurer = () => makeUser({ roles: ['leader'], permissions: ['canVerifyPayments'] });

describe('submitting payments', () => {
  it('a parent pays online with proof and verifiers are notified', async () => {
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const verifier = await treasurer();
    const fee = await classFee(scout.id);

    const payment = await submitPayment(fee, parent, '50.00', 'online', png());
    expect(payment.status).toBe('AwaitingVerification');
    expect((await reload(fee.id)).status).toBe('AwaitingVerification');
    expect((await reload(fee.id)).paidAmount).toBe('0.00');

    const [proof] = await db.select().from(schema.paymentProofs);
    expect(proof.path).toBe(`payment-proofs/${payment.uuid}.png`);
    expect(await get('local', proof.path)).not.toBeNull();
    expect(await unreadCount(verifier.id)).toBe(1);
    expect((await list(verifier.id))[0].data.title).toBe('Payment waiting for verification');
  });

  it('online payment requires a proof', async () => {
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const fee = await classFee(scout.id);
    await expect(submitPayment(fee, parent, '50', 'online', null)).rejects.toThrow('Online payment requires a proof file.');
    expect(await db.select().from(schema.payments)).toHaveLength(0);
  });

  it('a bad or too large proof creates no payment and no balance change', async () => {
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const fee = await classFee(scout.id);
    await expect(submitPayment(fee, parent, '50', 'online', new File(['plain text'], 'proof.png'))).rejects.toBeTruthy();
    await setSetting('proof_max_kb', '1');
    await expect(submitPayment(fee, parent, '50', 'online', new File([new Uint8Array(2048)], 'proof.png'))).rejects.toBeTruthy();
    expect(await db.select().from(schema.payments)).toHaveLength(0);
    expect((await reload(fee.id)).status).toBe('Pending');
  });

  it('staff cash is approved at once', async () => {
    const scout = await makeStudent();
    const staff = await treasurer();
    await makeGroup({ leaders: [staff], members: [scout] });
    const fee = await classFee(scout.id);
    const payment = await submitPayment(fee, staff, '20.00', 'cash');
    expect(payment.status).toBe('Paid');
    const after = await reload(fee.id);
    expect(after.paidAmount).toBe('20.00');
    expect(after.outstandingAmount).toBe('30.00');
    expect(after.status).toBe('Partial');
    await submitPayment(await reload(fee.id), staff, '30.00', 'cash');
    expect((await reload(fee.id)).status).toBe('Paid');
  });

  it('parents and students cannot record cash', async () => {
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const fee = await classFee(scout.id);
    await expect(submitPayment(fee, parent, '10', 'cash')).rejects.toThrow('Only authorised staff can record a cash payment.');
  });

  it("a parent cannot pay for someone else's child", async () => {
    const mine = await makeStudent();
    const other = await makeStudent();
    const parent = await makeParentOf(mine);
    const fee = await classFee(other.id);
    expect(await canPay(parent, fee)).toBe(false);
    await expect(submitPayment(fee, parent, '10', 'online', png())).rejects.toThrow(NotAccessibleError);
  });

  it('refuses tiny amounts and payables that are paid or void', async () => {
    const scout = await makeStudent();
    const staff = await makeAdmin();
    const fee = await classFee(scout.id, '10.00');
    await expect(submitPayment(fee, staff, '0', 'cash')).rejects.toThrow('Enter an amount of at least 0.01.');
    await submitPayment(fee, staff, '10', 'cash');
    await expect(submitPayment(await reload(fee.id), staff, '1', 'cash')).rejects.toThrow('This is already fully paid.');
    const voided = await classFee((await makeStudent()).id);
    await db.update(schema.classFees).set({ status: 'Void' }).where(eq(schema.classFees.id, voided.id));
    await expect(submitPayment(await reload(voided.id), staff, '1', 'cash')).rejects.toThrow('This class fee was voided and cannot be paid.');
  });
});

describe('deciding payments', () => {
  async function awaiting() {
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const fee = await classFee(scout.id);
    const payment = await submitPayment(fee, parent, '50.00', 'online', png());
    return { fee, parent, payment };
  }

  it('approval counts the payment; rejection does not', async () => {
    const admin = await makeAdmin();
    const a = await awaiting();
    await approvePayment(a.payment.id, admin);
    const paid = await reload(a.fee.id);
    expect(paid.status).toBe('Paid');
    expect(paid.paidAmount).toBe('50.00');

    const b = await awaiting();
    await rejectPayment(b.payment.id, admin, 'Blurry proof');
    const rejected = await reload(b.fee.id);
    expect(rejected.status).toBe('Pending');
    expect(rejected.paidAmount).toBe('0.00');
    const [row] = await db.select().from(schema.payments).where(eq(schema.payments.id, b.payment.id));
    expect(row.rejectionReason).toBe('Blurry proof');
  });

  it('rejection needs a reason', async () => {
    const a = await awaiting();
    await expect(rejectPayment(a.payment.id, await makeAdmin(), '  ')).rejects.toThrow('A reason is required to reject a payment.');
  });

  it('only verifiers can decide', async () => {
    const a = await awaiting();
    await expect(approvePayment(a.payment.id, await makeLeader())).rejects.toThrow(ScoutError);
    await expect(approvePayment(a.payment.id, a.parent)).rejects.toThrow('Only admins or users with the Verify payments permission');
  });

  it('a payment can only be decided once', async () => {
    const admin = await makeAdmin();
    const a = await awaiting();
    await approvePayment(a.payment.id, admin);
    await expect(approvePayment(a.payment.id, admin)).rejects.toThrow('Only payments awaiting verification can be approved.');
    await expect(rejectPayment(a.payment.id, admin, 'late')).rejects.toThrow('Only payments awaiting verification can be rejected.');
  });

  it('the decision notifies the submitter', async () => {
    const admin = await makeAdmin();
    const a = await awaiting();
    await approvePayment(a.payment.id, admin);
    expect((await list(a.parent.id))[0].data.title).toBe('Payment approved');
    const b = await awaiting();
    await rejectPayment(b.payment.id, admin, 'Wrong amount');
    const note = (await list(b.parent.id))[0].data;
    expect(note.title).toBe('Payment rejected');
    expect(note.body).toContain('Wrong amount');
  });
});
