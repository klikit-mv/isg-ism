import { and, count, desc, eq, gte, inArray, like, lte } from 'drizzle-orm';
import { db, schema } from '@/db';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import { markableStudentIds, type ActivityRow } from './activities';
import { SOURCE_ROSTER } from './payments';
import type { AuthUser } from './users';

export interface RegisterRow {
  studentId: number;
  name: string;
  section: string;
  indexNumber: string;
  status: string;
  remarks: string;
  /** '', '0', '5', '10', '15' or 'other'. */
  payment: string;
  other: string;
  fee: { amount: string; paid: string } | null;
}

/** The rows of the attendance register: this user's markable scouts with any saved marks and fees. */
export async function registerRows(activity: ActivityRow, user: AuthUser): Promise<RegisterRow[]> {
  const ids = await markableStudentIds(activity, user);
  if (!ids.length) return [];
  const [students, records, fees] = await Promise.all([
    db.select().from(schema.students).where(inArray(schema.students.id, ids)).orderBy(schema.students.name),
    db.select().from(schema.attendanceRecords).where(eq(schema.attendanceRecords.activityId, activity.id)),
    db.select().from(schema.classFees).where(eq(schema.classFees.activityId, activity.id)),
  ]);
  const feeIds = fees.map((f) => f.id);
  const roster = feeIds.length
    ? await db.select().from(schema.payments).where(and(eq(schema.payments.payableType, 'class_fee'), inArray(schema.payments.payableId, feeIds), eq(schema.payments.source, SOURCE_ROSTER), eq(schema.payments.status, 'Paid')))
    : [];

  return students.map((s) => {
    const record = records.find((r) => r.studentId === s.id);
    const fee = fees.find((f) => f.studentId === s.id);
    const paid = fee ? roster.find((p) => p.payableId === fee.id)?.amount : undefined;
    let payment = '';
    let other = '';
    if (paid !== undefined) {
      const whole = paid.replace(/0+$/, '').replace(/\.$/, '');
      if (['5', '10', '15'].includes(whole)) payment = whole;
      else [payment, other] = ['other', paid];
    } else if (record && fee) payment = '0';
    return {
      studentId: s.id, name: s.name, section: s.section, indexNumber: s.indexNumber,
      status: record?.status ?? '', remarks: record?.remarks ?? '', payment, other,
      fee: fee ? { amount: fee.amount, paid: fee.paidAmount } : null,
    };
  });
}

export interface HistoryFilters { q?: string; child?: string; status?: string; from?: string; to?: string; page: number }

/** Attendance records for families and scouts, with text, child, status and date filters. */
export async function attendanceHistory(studentIds: number[], f: HistoryFilters) {
  const R = schema.attendanceRecords;
  const A = schema.activities;
  const S = schema.students;
  const where = and(
    inArray(R.studentId, studentIds.length ? studentIds : [0]),
    f.q ? like(A.name, `%${f.q}%`) : undefined,
    f.child ? eq(S.uuid, f.child) : undefined,
    f.status ? eq(R.status, f.status) : undefined,
    f.from ? gte(A.date, f.from) : undefined,
    f.to ? lte(A.date, f.to) : undefined,
  );
  const base = db.select({ n: count() }).from(R).innerJoin(A, eq(A.id, R.activityId)).innerJoin(S, eq(S.id, R.studentId));
  const [{ n }] = await base.where(where);
  const rows = await db.select({ date: A.date, activity: A.name, student: S.name, status: R.status, remarks: R.remarks, id: R.id }).from(R)
    .innerJoin(A, eq(A.id, R.activityId)).innerJoin(S, eq(S.id, R.studentId)).where(where)
    .orderBy(desc(A.date), desc(R.id)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);
  return pageOf(rows, Number(n), f.page);
}
