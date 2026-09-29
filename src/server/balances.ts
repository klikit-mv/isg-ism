import { and, eq } from 'drizzle-orm';
import { db, schema, type DbLike } from '@/db';
import * as money from '@/lib/money';
import { loadPayableOrThrow, paymentsOf, type PayableType } from './payables';

/**
 * Balances are never typed in: paid, outstanding and status always derive
 * from approved (Paid) payments.
 */
export async function approvedTotal(type: PayableType, id: number, conn: DbLike = db): Promise<string> {
  const rows = await conn.select({ amount: schema.payments.amount }).from(schema.payments).where(and(paymentsOf(type, id), eq(schema.payments.status, 'Paid')));
  return money.add('0', ...rows.map((r) => r.amount));
}

export async function hasAwaiting(type: PayableType, id: number, conn: DbLike = db): Promise<boolean> {
  const [row] = await conn.select({ id: schema.payments.id }).from(schema.payments).where(and(paymentsOf(type, id), eq(schema.payments.status, 'AwaitingVerification'))).limit(1);
  return !!row;
}

/** [outstanding, status] for an amount due, the approved total and any awaiting payment. */
export function derive(amount: string, paid: string, awaiting: boolean): [string, string] {
  const outstanding = money.max('0', money.sub(amount, paid));
  const status =
    money.compare(paid, amount) >= 0 && money.isPositive(amount) ? 'Paid'
    : money.isPositive(paid) ? 'Partial'
    : awaiting ? 'AwaitingVerification'
    : 'Pending';
  return [outstanding, status];
}

/** Drive purchase_status from payment_status. Cancelled is never touched. */
export function purchaseStatusFor(paymentStatus: string, current: string): string {
  if (current === 'Cancelled') return current;
  if (paymentStatus === 'Paid') return ['ReadyForCollection', 'Delivered'].includes(current) ? current : 'Confirmed';
  if (paymentStatus === 'AwaitingVerification') return 'PaymentVerification';
  return 'PendingPayment';
}

/** Recompute paid, outstanding and status of a payable from its payments. */
export async function recalculate(type: PayableType, id: number, conn: DbLike = db): Promise<void> {
  const paid = await approvedTotal(type, id, conn);
  const awaiting = await hasAwaiting(type, id, conn);
  const payable = await loadPayableOrThrow(type, { id }, conn);

  switch (type) {
    case 'class_fee': {
      if (payable.status === 'Void') {
        await conn.update(schema.classFees).set({ paidAmount: paid, outstandingAmount: '0.00', updatedAt: new Date() }).where(eq(schema.classFees.id, id));
        return;
      }
      const [outstanding, status] = derive(payable.amountDue, paid, awaiting);
      await conn.update(schema.classFees).set({ paidAmount: paid, outstandingAmount: outstanding, status, updatedAt: new Date() }).where(eq(schema.classFees.id, id));
      return;
    }
    case 'annual_fee': {
      const [outstanding, status] = derive(payable.amountDue, paid, awaiting);
      await conn.update(schema.annualFees).set({ paidAmount: paid, outstandingAmount: outstanding, status, updatedAt: new Date() }).where(eq(schema.annualFees.id, id));
      return;
    }
    case 'purchase': {
      const [outstanding, status] = derive(payable.amountDue, paid, awaiting);
      const [row] = await conn.select({ s: schema.purchases.purchaseStatus }).from(schema.purchases).where(eq(schema.purchases.id, id));
      await conn.update(schema.purchases).set({ paidAmount: paid, outstandingAmount: outstanding, paymentStatus: status, purchaseStatus: purchaseStatusFor(status, row.s), updatedAt: new Date() }).where(eq(schema.purchases.id, id));
      return;
    }
    case 'event_registration': {
      let [outstanding, status] = derive(payable.amountDue, paid, awaiting);
      // A free registration (nothing to pay) counts as paid.
      if (!money.isPositive(payable.amountDue)) status = 'Paid';
      await conn.update(schema.eventRegistrations).set({ paidAmount: paid, outstandingAmount: outstanding, paymentStatus: status, updatedAt: new Date() }).where(eq(schema.eventRegistrations.id, id));
      return;
    }
  }
}
