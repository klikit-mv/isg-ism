import { randomUUID } from 'node:crypto';
import { eq, and, or, isNull, ne } from 'drizzle-orm';
import { db, schema, type DbLike } from '@/db';
import * as money from '@/lib/money';
import type { PaymentMethodValue } from '@/lib/enums';
import { recordAudit } from './audit';
import { recalculate } from './balances';
import { NotAccessibleError, ScoutError } from './errors';
import { decrementForPurchase } from './inventory';
import { notify } from './notifications';
import { isPayableType, loadPayable, loadPayableOrThrow, paymentsOf, type PayableInfo, type PayableType } from './payables';
import { canAccessStudentId } from './scope';
import { proofMaxKb, proofMimes } from './settings';
import { put, remove } from './storage';
import { readProof } from './uploads';
import { hasPermission, isActive, isAdmin, loadUser, type AuthUser } from './users';

export const SOURCE_ROSTER = 'roster';

/** Admins, or users with the Verify payments permission, record cash and decide payments. */
export const canRecordCash = (user: AuthUser) => isActive(user) && hasPermission(user, 'canVerifyPayments');

export async function canPay(user: AuthUser, payable: PayableInfo): Promise<boolean> {
  if (payable.type === 'annual_fee' && payable.studentId === null) return isAdmin(user) || payable.userId === user.id;
  if (payable.type === 'event_registration' && payable.studentId === null) return isAdmin(user) || payable.userId === user.id || canRecordCash(user);
  return payable.studentId !== null && (await canAccessStudentId(user, payable.studentId));
}

function assertPayable(payable: PayableInfo) {
  if (payable.type === 'class_fee' && payable.status === 'Void') throw new ScoutError('This class fee was voided and cannot be paid.');
  if (payable.type === 'purchase' && payable.closed) throw new ScoutError('This purchase was cancelled.');
  if (payable.type === 'event_registration' && payable.closed) throw new ScoutError('This event registration was cancelled.');
  if (payable.status === 'Paid') throw new ScoutError('This is already fully paid.');
}

const assertVerifier = (actor: AuthUser) => {
  if (!canRecordCash(actor)) throw new ScoutError('Only admins or users with the Verify payments permission can approve or reject payments.');
};

/** After a payment changes: refresh balances and take stock when a purchase is fully paid. */
async function settle(type: PayableType, id: number, actor: AuthUser | null, conn: DbLike) {
  await recalculate(type, id, conn);
  if (type === 'purchase') await decrementForPurchase(id, actor, conn);
}

/** Submit a payment: online with proof (awaits verification), or staff cash (approved at once). */
export async function submitPayment(payable: PayableInfo, actor: AuthUser, amount: string, method: PaymentMethodValue, proof?: File | null) {
  if (!(await canPay(actor, payable))) throw new NotAccessibleError('You cannot pay for this record.');
  if (method === 'cash' && !canRecordCash(actor)) throw new ScoutError('Only authorised staff can record a cash payment.');
  if (method === 'online' && !proof) throw new ScoutError('Online payment requires a proof file.', 'proof');
  if (money.compare(amount, '0.01') < 0) throw new ScoutError('Enter an amount of at least 0.01.', 'amount');
  assertPayable(payable);

  const uuid = randomUUID();
  let stored: { path: string; name: string; mime: string; size: number } | null = null;
  if (proof) {
    const read = await readProof(proof, await proofMimes(), await proofMaxKb());
    stored = { path: await put('local', `payment-proofs/${uuid}.${read.ext}`, read.bytes), name: proof.name.slice(0, 255), mime: read.mime, size: read.bytes.length };
  }

  try {
    const now = new Date();
    const payment = await db.transaction(async (tx) => {
      const staffCash = method === 'cash';
      const [ins] = await tx.insert(schema.payments).values({
        uuid, payableType: payable.type, payableId: payable.id, studentId: payable.studentId, amount: money.normalize(amount), method,
        submittedBy: actor.id, submittedAt: now, status: staffCash ? 'Paid' : 'AwaitingVerification',
        acceptedBy: staffCash ? actor.id : null, verifiedBy: staffCash ? actor.id : null, verifiedAt: staffCash ? now : null, createdAt: now, updatedAt: now,
      }).$returningId();
      if (stored) {
        await tx.insert(schema.paymentProofs).values({
          paymentId: ins.id, disk: 'local', path: stored.path, originalFilename: stored.name, mimeType: stored.mime, fileSize: stored.size,
          uploadedBy: actor.id, uploadedAt: now, createdAt: now, updatedAt: now,
        });
      }
      await recordAudit('payment.submitted', { type: 'payment', id: uuid }, { type: payable.type, amount: money.normalize(amount), method, status: staffCash ? 'Paid' : 'AwaitingVerification' }, actor.id, tx);
      await settle(payable.type, payable.id, actor, tx);
      const [row] = await tx.select().from(schema.payments).where(eq(schema.payments.id, ins.id));
      return row;
    });

    if (payment.status === 'AwaitingVerification') {
      let name = actor.name;
      if (payment.studentId) name = (await db.select({ n: schema.students.name }).from(schema.students).where(eq(schema.students.id, payment.studentId)))[0]?.n ?? name;
      await notify.paymentSubmitted(payment, name);
    }
    return payment;
  } catch (error) {
    if (stored) await remove('local', stored.path);
    throw error;
  }
}

async function decide(paymentId: number, actor: AuthUser, verb: 'approve' | 'reject', reason?: string) {
  assertVerifier(actor);
  const now = new Date();
  const payment = await db.transaction(async (tx) => {
    const [locked] = await tx.select().from(schema.payments).where(eq(schema.payments.id, paymentId)).for('update');
    if (!locked) throw new ScoutError('That payment no longer exists.');
    if (locked.status !== 'AwaitingVerification') throw new ScoutError(`Only payments awaiting verification can be ${verb === 'approve' ? 'approved' : 'rejected'}.`);

    if (verb === 'approve') {
      await tx.update(schema.payments).set({ status: 'Paid', verifiedBy: actor.id, verifiedAt: now, acceptedBy: actor.id, updatedAt: now }).where(eq(schema.payments.id, locked.id));
    } else {
      await tx.update(schema.payments).set({ status: 'Rejected', rejectionReason: reason, verifiedBy: actor.id, verifiedAt: now, updatedAt: now }).where(eq(schema.payments.id, locked.id));
    }
    await settle(locked.payableType as PayableType, locked.payableId, actor, tx);
    await recordAudit(verb === 'approve' ? 'payment.approved' : 'payment.rejected', { type: 'payment', id: locked.uuid }, verb === 'approve' ? { amount: locked.amount } : { reason }, actor.id, tx);
    const [row] = await tx.select().from(schema.payments).where(eq(schema.payments.id, locked.id));
    return row;
  });
  const submitter = payment.submittedBy ? await loadUser(payment.submittedBy) : null;
  await notify.paymentDecided(submitter, payment);
  return payment;
}

export const approvePayment = (paymentId: number, actor: AuthUser) => decide(paymentId, actor, 'approve');

export async function rejectPayment(paymentId: number, actor: AuthUser, reason: string) {
  assertVerifier(actor);
  if (!reason.trim()) throw new ScoutError('A reason is required to reject a payment.', 'reason');
  return decide(paymentId, actor, 'reject', reason.trim());
}

/**
 * One auto-approved cash payment per class fee from the attendance roster.
 * A zero amount rejects the roster payment.
 */
export async function upsertRosterCash(fee: { id: number; studentId: number }, amount: string, actor: AuthUser, conn: DbLike = db) {
  const P = schema.payments;
  const [existing] = await conn.select().from(P).where(and(paymentsOf('class_fee', fee.id), eq(P.source, SOURCE_ROSTER))).for('update');
  const now = new Date();

  if (!money.isPositive(amount)) {
    if (existing && existing.status !== 'Rejected') {
      await conn.update(P).set({ status: 'Rejected', rejectionReason: 'Marked not paid on the attendance roster.', verifiedBy: actor.id, verifiedAt: now, updatedAt: now }).where(eq(P.id, existing.id));
      await recordAudit('payment.roster_rejected', { type: 'payment', id: existing.uuid }, {}, actor.id, conn);
    }
    return existing ?? null;
  }

  const attributes = {
    amount: money.normalize(amount), method: 'cash', status: 'Paid', rejectionReason: null, submittedBy: actor.id, submittedAt: now,
    acceptedBy: actor.id, verifiedBy: actor.id, verifiedAt: now, updatedAt: now,
  };
  if (existing) {
    await conn.update(P).set(attributes).where(eq(P.id, existing.id));
    await recordAudit('payment.roster_updated', { type: 'payment', id: existing.uuid }, { amount: attributes.amount }, actor.id, conn);
    return { ...existing, ...attributes };
  }
  const uuid = randomUUID();
  await conn.insert(P).values({ uuid, payableType: 'class_fee', payableId: fee.id, studentId: fee.studentId, source: SOURCE_ROSTER, createdAt: now, ...attributes });
  await recordAudit('payment.roster_recorded', { type: 'payment', id: uuid }, { amount: attributes.amount }, actor.id, conn);
  return null;
}

/** Approved payments on a class fee that did not come from the roster. */
export async function otherApprovedTotal(feeId: number, conn: DbLike = db): Promise<string> {
  const P = schema.payments;
  const rows = await conn.select({ amount: P.amount }).from(P).where(and(paymentsOf('class_fee', feeId), eq(P.status, 'Paid'), or(isNull(P.source), ne(P.source, SOURCE_ROSTER))));
  return money.add('0', ...rows.map((r) => r.amount));
}

export async function hasLivePayments(type: PayableType, id: number, conn: DbLike = db): Promise<boolean> {
  const P = schema.payments;
  const rows = await conn.select({ s: P.status }).from(P).where(paymentsOf(type, id));
  return rows.some((r) => r.s === 'Paid' || r.s === 'AwaitingVerification');
}

export { isPayableType, loadPayable, loadPayableOrThrow };
