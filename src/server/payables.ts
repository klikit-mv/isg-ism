import { and, eq } from 'drizzle-orm';
import { db, schema, type DbLike } from '@/db';

/**
 * Anything that can be paid for. Balances and status are derived from
 * approved payments (see balances.ts), never typed in.
 */
export const PAYABLE_TYPES = ['class_fee', 'annual_fee', 'purchase', 'event_registration'] as const;
export type PayableType = (typeof PAYABLE_TYPES)[number];

export const isPayableType = (value: unknown): value is PayableType => (PAYABLE_TYPES as readonly string[]).includes(String(value));

export const PAYABLE_LABELS: Record<PayableType, string> = {
  class_fee: 'Class fee', annual_fee: 'Annual fee', purchase: 'Shop purchase', event_registration: 'Event',
};

export interface PayableInfo {
  type: PayableType;
  id: number;
  uuid: string;
  studentId: number | null;
  /** Set for leaders' own annual fees and event registrations. */
  userId: number | null;
  amountDue: string;
  paidAmount: string;
  outstandingAmount: string;
  /** FeeStatus of the money side. */
  status: string;
  /** Voided, cancelled or otherwise no longer payable. */
  closed: boolean;
  description: string;
}

/** Load one payable by uuid or id, optionally locking the row (inside a transaction). */
export async function loadPayable(type: PayableType, by: { uuid: string } | { id: number }, conn: DbLike = db, lock = false): Promise<PayableInfo | null> {
  const pick = <T extends { id: any; uuid: any }>(table: T) => ('uuid' in by ? eq(table.uuid, by.uuid) : eq(table.id, by.id));
  const q = <T extends object>(query: T): T => (lock ? (query as any).for('update') : query);

  switch (type) {
    case 'class_fee': {
      const [row] = await q(conn.select().from(schema.classFees).where(pick(schema.classFees)).limit(1));
      if (!row) return null;
      const [activity] = await conn.select({ name: schema.activities.name }).from(schema.activities).where(eq(schema.activities.id, row.activityId));
      return { type, id: row.id, uuid: row.uuid, studentId: row.studentId, userId: null, amountDue: row.amount, paidAmount: row.paidAmount, outstandingAmount: row.outstandingAmount, status: row.status, closed: row.status === 'Void', description: `Class fee — ${activity?.name ?? 'activity'}` };
    }
    case 'annual_fee': {
      const [row] = await q(conn.select().from(schema.annualFees).where(pick(schema.annualFees)).limit(1));
      if (!row) return null;
      const [year] = await conn.select({ year: schema.annualFeeYears.year }).from(schema.annualFeeYears).where(eq(schema.annualFeeYears.id, row.annualFeeYearId));
      return { type, id: row.id, uuid: row.uuid, studentId: row.studentId, userId: row.userId, amountDue: row.amount, paidAmount: row.paidAmount, outstandingAmount: row.outstandingAmount, status: row.status, closed: row.status === 'Void', description: `Annual fee ${year?.year ?? ''}`.trim() };
    }
    case 'purchase': {
      const [row] = await q(conn.select().from(schema.purchases).where(pick(schema.purchases)).limit(1));
      if (!row) return null;
      const items = await conn.select({ name: schema.purchaseItems.itemNameSnapshot }).from(schema.purchaseItems).where(eq(schema.purchaseItems.purchaseId, row.id));
      return { type, id: row.id, uuid: row.uuid, studentId: row.studentId, userId: null, amountDue: row.totalAmount, paidAmount: row.paidAmount, outstandingAmount: row.outstandingAmount, status: row.paymentStatus, closed: row.purchaseStatus === 'Cancelled', description: `Shop purchase — ${items.map((i) => i.name).join(', ')}` };
    }
    case 'event_registration': {
      const [row] = await q(conn.select().from(schema.eventRegistrations).where(pick(schema.eventRegistrations)).limit(1));
      if (!row) return null;
      const [event] = await conn.select({ name: schema.events.name }).from(schema.events).where(eq(schema.events.id, row.eventId));
      let who = '';
      if (row.studentId) who = (await conn.select({ n: schema.students.name }).from(schema.students).where(eq(schema.students.id, row.studentId)))[0]?.n ?? '';
      else if (row.userId) who = (await conn.select({ n: schema.users.name }).from(schema.users).where(eq(schema.users.id, row.userId)))[0]?.n ?? '';
      return { type, id: row.id, uuid: row.uuid, studentId: row.studentId, userId: row.userId, amountDue: row.totalAmount, paidAmount: row.paidAmount, outstandingAmount: row.outstandingAmount, status: row.paymentStatus, closed: row.status !== 'registered', description: `Event — ${event?.name ?? 'registration'} (${who})` };
    }
  }
}

export async function loadPayableOrThrow(type: PayableType, by: { uuid: string } | { id: number }, conn: DbLike = db, lock = false): Promise<PayableInfo> {
  const info = await loadPayable(type, by, conn, lock);
  if (!info) throw new Error(`Payable ${type} not found.`);
  return info;
}

export const paymentsOf = (type: PayableType, id: number) => and(eq(schema.payments.payableType, type), eq(schema.payments.payableId, id));
