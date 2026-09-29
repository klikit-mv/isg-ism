import { and, eq, ne } from 'drizzle-orm';
import { db, schema, type DbLike } from '@/db';
import * as money from '@/lib/money';
import { recordAudit } from './audit';
import { recalculate } from './balances';
import { ScoutError } from './errors';
import { hasLivePayments, otherApprovedTotal, upsertRosterCash } from './payments';
import { defaultClassFee } from './settings';
import type { AuthUser } from './users';

type Activity = typeof schema.activities.$inferSelect;
type Student = { id: number; uuid: string };
type ClassFee = typeof schema.classFees.$inferSelect;

/** Class fees follow attendance: one per activity per scout, created, voided or re-opened as marks change. */
export async function amountFor(activity: Pick<Activity, 'feeAmount'>): Promise<string> {
  return activity.feeAmount !== null && money.isPositive(activity.feeAmount) ? money.normalize(activity.feeAmount) : defaultClassFee();
}

const addDays = (date: string, days: number): string => {
  const d = new Date(`${date}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
};

/** Apply the class-fee rules for one attendance mark. Call inside the attendance transaction. */
export async function syncForAttendance(activity: Activity, student: Student, status: string, actor: AuthUser, conn: DbLike): Promise<ClassFee | null> {
  if (!activity.chargeFee) return null;
  const F = schema.classFees;
  const [fee] = await conn.select().from(F).where(and(eq(F.activityId, activity.id), eq(F.studentId, student.id))).for('update');
  const now = new Date();

  if (status !== 'Excused') {
    if (!fee) {
      const amount = await amountFor(activity);
      const [ins] = await conn.insert(F).values({
        activityId: activity.id, studentId: student.id, amount, paidAmount: '0.00', outstandingAmount: amount, status: 'Pending',
        dueDate: addDays(activity.date, 14), createdBy: actor.id, createdAt: now, updatedAt: now,
      }).$returningId();
      await recordAudit('class_fee.created', { type: 'class_fee', id: ins.id }, { amount }, actor.id, conn);
      return (await conn.select().from(F).where(eq(F.id, ins.id)))[0];
    }
    if (fee.status === 'Void') {
      await conn.update(F).set({ status: 'Pending', amount: await amountFor(activity), voidedAt: null, updatedAt: now }).where(eq(F.id, fee.id));
      await recalculate('class_fee', fee.id, conn);
      await recordAudit('class_fee.reopened', { type: 'class_fee', id: fee.id }, {}, actor.id, conn);
      return (await conn.select().from(F).where(eq(F.id, fee.id)))[0];
    }
    return fee;
  }

  // Excused: void an unpaid fee; a fee with any payment is left in place.
  if (fee && fee.status !== 'Void' && !money.isPositive(fee.paidAmount) && !(await hasLivePayments('class_fee', fee.id, conn))) {
    await conn.update(F).set({ status: 'Void', outstandingAmount: '0.00', voidedAt: now, updatedAt: now }).where(eq(F.id, fee.id));
    await recordAudit('class_fee.voided', { type: 'class_fee', id: fee.id }, {}, actor.id, conn);
  }
  return fee ?? null;
}

/** Change the fee due on a charged activity and re-price every non-void fee. */
export async function updateActivityFee(activity: Activity, amount: string, actor: AuthUser): Promise<number> {
  if (!activity.chargeFee) throw new ScoutError('This activity does not charge a fee.');
  if (money.compare(amount, '0') < 0) throw new ScoutError('The fee cannot be negative.');
  return db.transaction(async (tx) => {
    const F = schema.classFees;
    await tx.update(schema.activities).set({ feeAmount: money.normalize(amount), updatedAt: new Date() }).where(eq(schema.activities.id, activity.id));
    const fees = await tx.select().from(F).where(and(eq(F.activityId, activity.id), ne(F.status, 'Void'))).for('update');
    for (const fee of fees) {
      await tx.update(F).set({ amount: money.normalize(amount), updatedAt: new Date() }).where(eq(F.id, fee.id));
      await recalculate('class_fee', fee.id, tx);
    }
    await recordAudit('activity.fee_updated', { type: 'activity', id: activity.uuid }, { from: activity.feeAmount, to: money.normalize(amount), fees: fees.length }, actor.id, tx);
    return fees.length;
  });
}

export const ROSTER_PAYMENT_CHOICES: Record<string, string> = { '0': 'Not paid', '5': '5', '10': '10', '15': '15', other: 'Other' };

/**
 * Record the cash taken while marking. The amount is capped at the fee due
 * less other approved (non-roster) payments. Zero rejects the roster payment.
 */
export async function settleRosterPayment(fee: ClassFee, choice: string, actor: AuthUser, conn: DbLike): Promise<void> {
  if (fee.status === 'Void') return;
  const otherPaid = await otherApprovedTotal(fee.id, conn);
  const room = money.max('0', money.sub(fee.amount, otherPaid));
  const amount = money.min(money.max('0', choice), room);
  await upsertRosterCash(fee, amount, actor, conn);
  await recalculate('class_fee', fee.id, conn);
}
