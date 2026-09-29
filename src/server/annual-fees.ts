import { and, asc, count, desc, eq, inArray, isNull, like, or, sql } from 'drizzle-orm';
import { db, schema } from '@/db';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import * as money from '@/lib/money';
import { ScoutSection, type ScoutSectionValue } from '@/lib/enums';
import { todayLocal } from '@/lib/dates';
import { recordAudit } from './audit';
import { ScoutError } from './errors';
import { leaderStudentIds } from './scope';
import type { AuthUser } from './users';

export type FeeYearRow = typeof schema.annualFeeYears.$inferSelect;

export async function createYear(year: number, amount: string, actor: AuthUser): Promise<FeeYearRow> {
  const now = new Date();
  const [ins] = await db.insert(schema.annualFeeYears).values({ year, amount, status: 'Active', createdBy: actor.id, updatedBy: actor.id, createdAt: now, updatedAt: now }).$returningId();
  await recordAudit('annual_fee_year.created', { type: 'annual_fee_year', id: ins.id }, { year, amount }, actor.id);
  return (await db.select().from(schema.annualFeeYears).where(eq(schema.annualFeeYears.id, ins.id)))[0];
}

export async function setYearStatus(year: FeeYearRow, status: 'Active' | 'Inactive', actor: AuthUser): Promise<void> {
  await db.update(schema.annualFeeYears).set({ status, updatedBy: actor.id, updatedAt: new Date() }).where(eq(schema.annualFeeYears.id, year.id));
  await recordAudit('annual_fee_year.status_changed', { type: 'annual_fee_year', id: year.id }, { status }, actor.id);
}

export async function suggestedYear(): Promise<number> {
  const used = new Set((await db.select({ y: schema.annualFeeYears.year }).from(schema.annualFeeYears)).map((r) => r.y));
  let year = Number(todayLocal().slice(0, 4));
  while (used.has(year)) year++;
  return year;
}

export interface InvoicePerson {
  key: string;
  type: 'Student' | 'Leader';
  id: number;
  name: string;
  nationalId: string;
  section: string | null;
  invoiced: boolean;
}

/** Every scout and leader who could be invoiced. */
export async function invoicePeople(year: FeeYearRow, includeInactive = false): Promise<InvoicePerson[]> {
  const F = schema.annualFees;
  const rows = await db.select({ s: F.studentId, u: F.userId }).from(F).where(eq(F.annualFeeYearId, year.id));
  const studentIds = new Set(rows.map((r) => r.s).filter((x): x is number => x !== null));
  const userIds = new Set(rows.map((r) => r.u).filter((x): x is number => x !== null));

  const S = schema.students;
  const students = await db.select().from(S).where(and(isNull(S.deletedAt), includeInactive ? undefined : eq(S.status, 'active'))).orderBy(asc(S.name));
  const leaderIds = (await db.select({ id: schema.userRoles.userId }).from(schema.userRoles).where(eq(schema.userRoles.role, 'leader'))).map((r) => r.id);
  const U = schema.users;
  const leaders = leaderIds.length
    ? await db.select().from(U).where(and(inArray(U.id, leaderIds), isNull(U.deletedAt), includeInactive ? undefined : eq(U.status, 'active'))).orderBy(asc(U.name))
    : [];
  return [
    ...students.map((s): InvoicePerson => ({ key: `s${s.id}`, type: 'Student', id: s.id, name: s.name, nationalId: s.nationalId, section: s.section, invoiced: studentIds.has(s.id) })),
    ...leaders.map((u): InvoicePerson => ({ key: `u${u.id}`, type: 'Leader', id: u.id, name: u.name, nationalId: u.nationalId, section: null, invoiced: userIds.has(u.id) })),
  ];
}

/** Idempotent: skip anyone already invoiced for the year. */
export async function generateInvoices(year: FeeYearRow, people: { type: 'Student' | 'Leader'; id: number; section?: string | null }[], actor: AuthUser) {
  if (year.status !== 'Active') throw new ScoutError('Only Active annual fee years can have fees generated.');
  const F = schema.annualFees;
  return db.transaction(async (tx) => {
    let created = 0;
    let skipped = 0;
    for (const person of people) {
      const leader = person.type === 'Leader';
      const [exists] = await tx.select({ id: F.id }).from(F).where(and(eq(F.annualFeeYearId, year.id), eq(leader ? F.userId : F.studentId, person.id))).for('update');
      if (exists) { skipped++; continue; }
      let section: string | null = null;
      if (!leader) {
        section = ScoutSection.is(person.section) ? (person.section as ScoutSectionValue) : (await tx.select({ s: schema.students.section }).from(schema.students).where(eq(schema.students.id, person.id)))[0]?.s ?? null;
      }
      const now = new Date();
      await tx.insert(F).values({
        annualFeeYearId: year.id, ...(leader ? { userId: person.id } : { studentId: person.id }), personType: person.type, section, amount: year.amount,
        paidAmount: '0.00', outstandingAmount: year.amount, status: 'Pending', createdBy: actor.id, createdAt: now, updatedAt: now,
      });
      created++;
    }
    await recordAudit('annual_fee.generated', { type: 'annual_fee_year', id: year.id }, { created, skipped }, actor.id, tx);
    return { created, skipped };
  });
}

export interface AnnualFilters { q?: string; year?: string; section?: string; status?: string; page: number }

export async function listAnnualFees(user: AuthUser, f: AnnualFilters) {
  const A = schema.annualFees;
  const S = schema.students;
  const U = schema.users;
  const Y = schema.annualFeeYears;
  const ids = await leaderStudentIds(user);
  const term = f.q?.trim();
  const like_ = term ? `%${term}%` : null;
  const where = and(
    ids === null ? undefined : or(inArray(A.studentId, ids.length ? ids : [0]), eq(A.userId, user.id)),
    like_ ? or(like(S.name, like_), like(S.nationalId, like_), like(U.name, like_), like(U.nationalId, like_)) : undefined,
    f.year ? eq(Y.year, Number(f.year)) : undefined,
    f.section ? or(eq(A.section, f.section), and(isNull(A.section), eq(S.section, f.section))) : undefined,
    f.status ? eq(A.status, f.status) : undefined,
  );
  const [stats] = await db.select({
    records: count(), paid: sql<number>`COALESCE(SUM(CASE WHEN ${A.status} = 'Paid' THEN 1 ELSE 0 END), 0)`, billed: sql<string>`COALESCE(SUM(${A.amount}), 0)`,
  }).from(A).leftJoin(S, eq(S.id, A.studentId)).leftJoin(U, eq(U.id, A.userId)).innerJoin(Y, eq(Y.id, A.annualFeeYearId)).where(where);
  const rows = await db.select({ fee: A, student: S.name, studentSection: S.section, user: U.name, year: Y.year }).from(A)
    .leftJoin(S, eq(S.id, A.studentId)).leftJoin(U, eq(U.id, A.userId)).innerJoin(Y, eq(Y.id, A.annualFeeYearId))
    .where(where).orderBy(desc(A.createdAt), desc(A.id)).limit(PAGE_SIZE).offset((f.page - 1) * PAGE_SIZE);
  return {
    page: pageOf(rows, Number(stats.records), f.page),
    stats: { records: Number(stats.records), paid: Number(stats.paid), billed: money.normalize(stats.billed) },
  };
}

export const annualYearOptions = async () => (await db.select({ y: schema.annualFeeYears.year }).from(schema.annualFeeYears).orderBy(desc(schema.annualFeeYears.year))).map((r) => String(r.y));
