import { getPool } from '@/db';
import { PAGE_SIZE } from '@/components/Pagination';
import { formatDate, formatDateTime } from '@/lib/dates';
import { AttendanceStatus, FeeStatus, PaymentMethod, PaymentStatus, PurchaseStatus, RoverAttendanceStatus, ScoutSection } from '@/lib/enums';
import * as money from '@/lib/money';
import { leaderStudentIds } from './scope';
import type { AuthUser } from './users';
import type { Dropdowns } from './xlsx';

export type ReportType = 'attendance' | 'rover-attendance' | 'annual-fees' | 'class-fees' | 'payments' | 'shop';

export interface ReportMeta {
  title: string;
  description: string;
  filters: ('status' | 'q' | 'from' | 'to' | 'year' | 'method')[];
  statuses: { value: string; label: string }[];
  totals: ('billed' | 'paid' | 'outstanding' | 'amount')[];
}

const feeOptions = FeeStatus.options();

export const CATALOG: Record<ReportType, ReportMeta> = {
  attendance: { title: 'Attendance', description: 'Every attendance mark with activity, scout and who marked it.', filters: ['status', 'q', 'from', 'to'], statuses: AttendanceStatus.options(), totals: [] },
  'rover-attendance': { title: 'Rover attendance', description: 'Rover register marks, required and optional.', filters: ['status'], statuses: RoverAttendanceStatus.options(), totals: [] },
  'annual-fees': { title: 'Annual fees', description: 'Annual fees for scouts and leaders with balances.', filters: ['status', 'year', 'q'], statuses: feeOptions, totals: ['billed', 'paid', 'outstanding'] },
  'class-fees': { title: 'Class fees', description: 'Class fees from attendance, including voided ones.', filters: ['status', 'q'], statuses: feeOptions, totals: ['billed', 'paid', 'outstanding'] },
  payments: { title: 'Payments', description: 'All payments with method, status and verifier.', filters: ['status', 'method', 'q'], statuses: PaymentStatus.options(), totals: ['amount'] },
  shop: { title: 'Shop', description: 'Purchase lines with payment and order status.', filters: ['status'], statuses: PurchaseStatus.options(), totals: ['amount'] },
};

export const isReportType = (t: string): t is ReportType => t in CATALOG;

export const HEADINGS: Record<ReportType, string[]> = {
  attendance: ['Date', 'Activity', 'Student', 'Section', 'Status', 'Remarks', 'Marked by'],
  'rover-attendance': ['Activity', 'Rover', 'Status', 'Required/Optional', 'Marked by', 'Date'],
  'annual-fees': ['Year', 'Scout/Leader', 'Section', 'Fee', 'Paid', 'Outstanding', 'Status'],
  'class-fees': ['Activity', 'Student', 'Fee', 'Paid', 'Outstanding', 'Status'],
  payments: ['Payment id', 'Type', 'Student', 'Amount', 'Method', 'Status', 'Submitted', 'Verified', 'Verifier'],
  shop: ['Purchase', 'Item', 'Quantity', 'Customer', 'Amount', 'Payment status', 'Purchase status', 'Date'],
};

const labels = (list: { label: string }[]) => list.map((o) => o.label);

/** Dropdown lists for the exported sheet (labels, because that is what the cells contain). */
export const REPORT_DROPDOWNS: Record<ReportType, Dropdowns> = {
  attendance: { Section: ScoutSection.values, Status: AttendanceStatus.values },
  'rover-attendance': { Status: RoverAttendanceStatus.values, 'Required/Optional': ['Required', 'Optional'] },
  'annual-fees': { Section: ScoutSection.values, Status: labels(feeOptions) },
  'class-fees': { Status: labels(feeOptions) },
  payments: { Type: ['Class fee', 'Annual fee', 'Purchase', 'Event registration'], Method: labels(PaymentMethod.options()), Status: labels(PaymentStatus.options()) },
  shop: { 'Payment status': labels(feeOptions), 'Purchase status': labels(PurchaseStatus.options()) },
};

export interface ReportFilters { status?: string; q?: string; from?: string; to?: string; year?: string; method?: string }
type Raw = Record<string, any>;

interface Def { from: string; select: string; order: string; where(f: ReportFilters, add: (clause: string, ...params: unknown[]) => void): void; scope: string }

const like = (q: string) => `%${q}%`;

const DEFS: Record<ReportType, Def> = {
  attendance: {
    from: 'attendance_records r JOIN activities a ON a.id = r.activity_id JOIN students s ON s.id = r.student_id LEFT JOIN users m ON m.id = r.marked_by',
    select: 'a.date AS date, a.name AS activity, s.name AS student, s.section AS section, r.status AS status, r.remarks AS remarks, m.name AS marked_by',
    order: 'a.date DESC, s.name', scope: 'r.student_id',
    where: (f, add) => {
      if (f.status) add('r.status = ?', f.status);
      if (f.q) add('(a.name LIKE ? OR s.name LIKE ?)', like(f.q), like(f.q));
      if (f.from) add('a.date >= ?', f.from);
      if (f.to) add('a.date <= ?', f.to);
    },
  },
  'rover-attendance': {
    from: 'rover_attendance_records r JOIN activities a ON a.id = r.activity_id JOIN students s ON s.id = r.student_id LEFT JOIN users m ON m.id = r.marked_by',
    select: 'a.name AS activity, s.name AS rover, r.status AS status, r.is_required AS is_required, m.name AS marked_by, a.date AS date',
    order: 'a.date DESC, s.name', scope: 'r.student_id',
    where: (f, add) => { if (f.status) add('r.status = ?', f.status); },
  },
  'annual-fees': {
    from: 'annual_fees f JOIN annual_fee_years y ON y.id = f.annual_fee_year_id LEFT JOIN students s ON s.id = f.student_id LEFT JOIN users u ON u.id = f.user_id',
    select: 'y.year AS year, COALESCE(s.name, u.name) AS person, COALESCE(f.section, s.section) AS section, f.amount AS amount, f.paid_amount AS paid_amount, f.outstanding_amount AS outstanding_amount, f.status AS status',
    order: 'y.year DESC, person', scope: 'f.student_id',
    where: (f, add) => {
      if (f.status) add('f.status = ?', f.status);
      if (f.year) add('y.year = ?', f.year);
      if (f.q) add('(s.name LIKE ? OR u.name LIKE ?)', like(f.q), like(f.q));
    },
  },
  'class-fees': {
    from: 'class_fees f JOIN activities a ON a.id = f.activity_id JOIN students s ON s.id = f.student_id',
    select: 'a.name AS activity, s.name AS student, f.amount AS amount, f.paid_amount AS paid_amount, f.outstanding_amount AS outstanding_amount, f.status AS status, a.date AS date',
    order: 'a.date DESC, s.name', scope: 'f.student_id',
    where: (f, add) => {
      if (f.status) add('f.status = ?', f.status);
      if (f.q) add('(a.name LIKE ? OR s.name LIKE ?)', like(f.q), like(f.q));
    },
  },
  payments: {
    from: 'payments p LEFT JOIN students s ON s.id = p.student_id LEFT JOIN users v ON v.id = p.verified_by',
    select: 'p.uuid AS uuid, p.payable_type AS payable_type, s.name AS student, p.amount AS amount, p.method AS method, p.status AS status, p.submitted_at AS submitted_at, p.verified_at AS verified_at, v.name AS verifier',
    order: 'p.submitted_at DESC', scope: 'p.student_id',
    where: (f, add) => {
      if (f.status) add('p.status = ?', f.status);
      if (f.method) add('p.method = ?', f.method);
      if (f.q) add('(p.uuid LIKE ? OR s.name LIKE ?)', like(f.q), like(f.q));
    },
  },
  shop: {
    from: 'purchase_items i JOIN purchases p ON p.id = i.purchase_id JOIN students s ON s.id = p.student_id',
    select: 'p.uuid AS uuid, i.item_name_snapshot AS item, i.quantity AS quantity, s.name AS customer, i.total_amount AS amount, p.payment_status AS payment_status, p.purchase_status AS purchase_status, p.created_at AS created_at',
    order: 'p.created_at DESC', scope: 'p.student_id',
    where: (f, add) => { if (f.status) add('p.purchase_status = ?', f.status); },
  },
};

/** Which filters a report accepts; anything else in the query string is ignored. */
export function cleanFilters(type: ReportType, raw: Record<string, string | undefined>): ReportFilters {
  const out: ReportFilters = {};
  for (const key of CATALOG[type].filters) if (raw[key]) out[key] = raw[key];
  return out;
}

async function whereFor(type: ReportType, f: ReportFilters, user: AuthUser) {
  const def = DEFS[type];
  const clauses: string[] = [];
  const params: unknown[] = [];
  def.where(f, (clause, ...p) => { clauses.push(clause); params.push(...p); });
  const ids = await leaderStudentIds(user);
  if (ids !== null) {
    const list = ids.length ? ids : [0];
    const inList = `${def.scope} IN (${list.map(() => '?').join(',')})`;
    if (type === 'annual-fees') { clauses.push(`(${inList} OR f.user_id = ?)`); params.push(...list, user.id); }
    else { clauses.push(inList); params.push(...list); }
  }
  return { sql: clauses.length ? `WHERE ${clauses.join(' AND ')}` : '', params };
}

const label = (list: { value: string; label: string }[], value: string) => list.find((o) => o.value === value)?.label ?? value;
const typeLabel = (t: string) => { const s = t.replace(/_/g, ' '); return s.charAt(0).toUpperCase() + s.slice(1); };

/** One report row as the cells shown (and exported). */
export function mapRow(type: ReportType, r: Raw): string[] {
  const m = (v: unknown) => money.normalize(v as string);
  switch (type) {
    case 'attendance': return [formatDate(r.date), r.activity, r.student, r.section ?? '', r.status, r.remarks ?? '', r.marked_by ?? ''];
    case 'rover-attendance': return [r.activity, r.rover, r.status, r.is_required ? 'Required' : 'Optional', r.marked_by ?? '', formatDate(r.date)];
    case 'annual-fees': return [String(r.year), r.person ?? '', r.section ?? '', m(r.amount), m(r.paid_amount), m(r.outstanding_amount), label(feeOptions, r.status)];
    case 'class-fees': return [r.activity, r.student, m(r.amount), m(r.paid_amount), m(r.outstanding_amount), label(feeOptions, r.status)];
    case 'payments': return [r.uuid, typeLabel(r.payable_type), r.student ?? '', m(r.amount), label(PaymentMethod.options(), r.method), label(PaymentStatus.options(), r.status), formatDateTime(r.submitted_at), formatDateTime(r.verified_at), r.verifier ?? ''];
    case 'shop': return [String(r.uuid).slice(0, 8), r.item, String(r.quantity), r.customer, m(r.amount), label(feeOptions, r.payment_status), label(PurchaseStatus.options(), r.purchase_status), formatDate(r.created_at)];
  }
}

export async function reportPage(type: ReportType, f: ReportFilters, user: AuthUser, page: number) {
  const def = DEFS[type];
  const { sql, params } = await whereFor(type, f, user);
  const pool = getPool();
  const [[count]] = await pool.query<any[]>(`SELECT COUNT(*) AS n FROM ${def.from} ${sql}`, params);
  const [rows] = await pool.query<any[]>(`SELECT ${def.select} FROM ${def.from} ${sql} ORDER BY ${def.order} LIMIT ? OFFSET ?`, [...params, PAGE_SIZE, (page - 1) * PAGE_SIZE]);
  const n = Number(count.n);
  return { rows: rows.map((r) => mapRow(type, r)), total: n, page, pages: Math.max(1, Math.ceil(n / PAGE_SIZE)) };
}

/** Every row (up to `limit`), for export and print. */
export async function reportRows(type: ReportType, f: ReportFilters, user: AuthUser, limit = 20000): Promise<string[][]> {
  const def = DEFS[type];
  const { sql, params } = await whereFor(type, f, user);
  const [rows] = await getPool().query<any[]>(`SELECT ${def.select} FROM ${def.from} ${sql} ORDER BY ${def.order} LIMIT ?`, [...params, limit]);
  return rows.map((r) => mapRow(type, r));
}

export interface ReportTotals { rows: number; billed?: string; paid?: string; outstanding?: string; amount?: string }

export async function reportTotals(type: ReportType, f: ReportFilters, user: AuthUser): Promise<ReportTotals> {
  const def = DEFS[type];
  const { sql, params } = await whereFor(type, f, user);
  const sums = type === 'annual-fees' || type === 'class-fees'
    ? 'COALESCE(SUM(f.amount),0) AS billed, COALESCE(SUM(f.paid_amount),0) AS paid, COALESCE(SUM(f.outstanding_amount),0) AS outstanding'
    : type === 'payments' ? 'COALESCE(SUM(p.amount),0) AS amount' : type === 'shop' ? 'COALESCE(SUM(i.total_amount),0) AS amount' : '0 AS zero';
  const [[row]] = await getPool().query<any[]>(`SELECT COUNT(*) AS n, ${sums} FROM ${def.from} ${sql}`, params);
  const totals: ReportTotals = { rows: Number(row.n) };
  for (const key of ['billed', 'paid', 'outstanding', 'amount'] as const) if (row[key] !== undefined) totals[key] = money.normalize(row[key]);
  return totals;
}

/** The totals line at the bottom of an export. */
export function totalsRow(type: ReportType, totals: ReportTotals): string[] {
  const row = Array<string>(HEADINGS[type].length).fill('');
  row[0] = `Total (${totals.rows} rows)`;
  const positions: Partial<Record<keyof ReportTotals, number>> =
    type === 'annual-fees' ? { billed: 3, paid: 4, outstanding: 5 } : type === 'class-fees' ? { billed: 2, paid: 3, outstanding: 4 } : type === 'payments' ? { amount: 3 } : type === 'shop' ? { amount: 4 } : {};
  for (const [key, index] of Object.entries(positions)) row[index as number] = totals[key as keyof ReportTotals] as string;
  return row;
}
