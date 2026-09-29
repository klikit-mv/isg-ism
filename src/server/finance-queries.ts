import { and, asc, count, desc, eq, inArray, isNull, like, ne, or, sql } from 'drizzle-orm';
import { alias } from 'drizzle-orm/mysql-core';
import { db, schema } from '@/db';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import * as money from '@/lib/money';
import { loadPayable, isPayableType, type PayableType } from './payables';
import { activityScope, canAccessStudentId, leaderStudentIds, studentIdScope } from './scope';
import { hasPermission, type AuthUser } from './users';

export interface ClassFeeFilters { q?: string; activity?: string; section?: string; status?: string; page: number }

export async function listClassFees(user: AuthUser, f: ClassFeeFilters) {
  const C = schema.classFees;
  const S = schema.students;
  const A = schema.activities;
  const term = f.q?.trim();
  const like_ = term ? `%${term}%` : null;
  const where = and(
    ne(C.status, 'Void'),
    like_ ? or(like(S.name, like_), like(S.nationalId, like_), like(S.indexNumber, like_)) : undefined,
    f.activity ? eq(A.uuid, f.activity) : undefined,
    f.section ? eq(S.section, f.section) : undefined,
    f.status ? eq(C.status, f.status) : undefined,
    await studentIdScope(user, C.studentId),
  );
  const [stats] = await db.select({
    records: count(), paid: sql<number>`COALESCE(SUM(CASE WHEN ${C.status} = 'Paid' THEN 1 ELSE 0 END), 0)`,
    billed: sql<string>`COALESCE(SUM(${C.amount}), 0)`, outstanding: sql<string>`COALESCE(SUM(${C.outstandingAmount}), 0)`,
  }).from(C).innerJoin(S, eq(S.id, C.studentId)).innerJoin(A, eq(A.id, C.activityId)).where(where);
  const rows = await db.select({ fee: C, student: S.name, activity: A.name, date: A.date }).from(C)
    .innerJoin(S, eq(S.id, C.studentId)).innerJoin(A, eq(A.id, C.activityId)).where(where)
    .orderBy(desc(C.createdAt), desc(C.id)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);

  const activities = await db.select({ uuid: A.uuid, name: A.name }).from(A).where(and(isNull(A.deletedAt), eq(A.chargeFee, true), await activityScope(user))).orderBy(desc(A.date));
  return {
    page: pageOf(rows, Number(stats.records), f.page),
    stats: { records: Number(stats.records), paid: Number(stats.paid), billed: money.normalize(stats.billed), outstanding: money.normalize(stats.outstanding) },
    activities,
  };
}

export interface PaymentFilters { q?: string; status?: string; method?: string; page: number }

export async function listPayments(user: AuthUser, f: PaymentFilters) {
  const P = schema.payments;
  const S = schema.students;
  const Sub = alias(schema.users, 'submitter');
  const Ver = alias(schema.users, 'verifier');
  const term = f.q?.trim();
  const like_ = term ? `%${term}%` : null;
  let visible;
  if (!hasPermission(user, 'canVerifyPayments')) {
    const ids = (await leaderStudentIds(user)) ?? [];
    visible = or(inArray(P.studentId, ids.length ? ids : [0]), eq(P.submittedBy, user.id));
  }
  const where = and(
    like_ ? or(like(P.uuid, like_), like(S.name, like_), like(S.nationalId, like_)) : undefined,
    f.status ? eq(P.status, f.status) : undefined,
    f.method ? eq(P.method, f.method) : undefined,
    visible,
  );
  const [{ n }] = await db.select({ n: count() }).from(P).leftJoin(S, eq(S.id, P.studentId)).where(where);
  const rows = await db.select({ payment: P, student: S.name, submitter: Sub.name, verifier: Ver.name, proofUuid: schema.paymentProofs.uuid, proofMime: schema.paymentProofs.mimeType }).from(P)
    .leftJoin(S, eq(S.id, P.studentId)).leftJoin(Sub, eq(Sub.id, P.submittedBy)).leftJoin(Ver, eq(Ver.id, P.verifiedBy))
    .leftJoin(schema.paymentProofs, eq(schema.paymentProofs.paymentId, P.id))
    .where(where).orderBy(desc(P.submittedAt), desc(P.id)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), f.page);
}

/** Online payments waiting for a check, oldest first. */
export async function verificationQueue(page: number) {
  const P = schema.payments;
  const S = schema.students;
  const Sub = alias(schema.users, 'submitter');
  const where = eq(P.status, 'AwaitingVerification');
  const [{ n }] = await db.select({ n: count() }).from(P).where(where);
  const rows = await db.select({ payment: P, student: S.name, submitter: Sub.name, proof: schema.paymentProofs }).from(P)
    .leftJoin(S, eq(S.id, P.studentId)).leftJoin(Sub, eq(Sub.id, P.submittedBy)).leftJoin(schema.paymentProofs, eq(schema.paymentProofs.paymentId, P.id))
    .where(where).orderBy(asc(P.submittedAt), asc(P.id)).limit(PAGE_SIZE).offset((page - 1) * PAGE_SIZE);
  const enriched = [];
  for (const r of rows) {
    const payable = isPayableType(r.payment.payableType) ? await loadPayable(r.payment.payableType, { id: r.payment.payableId }) : null;
    enriched.push({ ...r, payable });
  }
  return pageOf(enriched, Number(n), page);
}

export async function findPaymentByUuid(uuid: string) {
  const [row] = await db.select().from(schema.payments).where(eq(schema.payments.uuid, uuid)).limit(1);
  return row ?? null;
}

/** May this user see the payment (and its proof)? */
export async function canViewPayment(user: AuthUser, payment: typeof schema.payments.$inferSelect): Promise<boolean> {
  if (user.status !== 'active') return false;
  if (hasPermission(user, 'canVerifyPayments') || payment.submittedBy === user.id) return true;
  if (payment.payableType === 'annual_fee') {
    const [fee] = await db.select({ userId: schema.annualFees.userId }).from(schema.annualFees).where(eq(schema.annualFees.id, payment.payableId));
    if (fee?.userId === user.id) return true;
  }
  return payment.studentId !== null && (await canAccessStudentId(user, payment.studentId));
}

export type { PayableType };
