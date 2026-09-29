import { randomBytes } from 'node:crypto';
import { and, eq, ne, or, inArray } from 'drizzle-orm';
import type { MySqlTable } from 'drizzle-orm/mysql-core';
import { db, schema, type Tx } from '@/db';
import { parseFlexibleDate } from '@/lib/dates';
import { Gender, ParentLinkStatus, Permission, PersonType, Role, ScoutSection, RoverAttendanceStatus, AttendanceStatus, FeeStatus, PaymentMethod, PaymentStatus, PurchaseStatus, RecordStatus, StudentStatus, UserStatus, type RoleValue } from '@/lib/enums';
import * as money from '@/lib/money';
import { recordAudit } from './audit';
import { recalculate } from './balances';
import { flushSettings } from './settings';
import { hashPin, type AuthUser } from './users';
import { buildWorkbook, normalizeHeader, readWorkbook, type Dropdowns, type ReadSheet } from './xlsx';

/** Sheet => required normalised columns, in import order. */
export const SHEETS: Record<string, string[]> = {
  Students: ['name', 'nationalid'], Users: ['name', 'nationalid'], ParentLinks: ['parent', 'student'], Groups: ['id', 'name'], GroupMembers: ['groupid', 'student'],
  GroupLeaders: ['groupid', 'user'], GroupAssistantLeaders: ['groupid', 'student'], Activities: ['id', 'name', 'date'], Attendance: ['activityid', 'student', 'status'],
  RoverAttendance: ['activityid', 'student', 'status'], ClassFeeConfig: ['activityid', 'amount'], ClassFees: ['activityid', 'student', 'amount'], Configuration: ['key', 'value'],
  UserPermissions: ['user', 'permission'], AnnualFeeConfig: ['year', 'amount'], AnnualFees: ['year', 'amount'], Payments: ['id', 'type', 'amount'], ShopItems: ['id', 'name', 'price'],
  Purchases: ['id', 'student', 'item', 'quantity'],
};

const ALIASES: Record<string, string[]> = {
  id: ['id', 'legacyid', 'key', 'uid'], nationalid: ['nationalid', 'nid', 'idcard', 'idcardno', 'idnumber'],
  student: ['student', 'studentid', 'studentnationalid', 'scoutid', 'scout'], parent: ['parent', 'parentid', 'parentnationalid', 'parentuserid'],
  user: ['user', 'userid', 'usernationalid', 'leader', 'leaderid', 'leadernationalid'], groupid: ['groupid', 'group'], activityid: ['activityid', 'activity'],
  item: ['item', 'itemid', 'shopitemid', 'shopitem'], indexnumber: ['indexnumber', 'index', 'indexno'], pinsalt: ['pinsalt', 'salt'], pinhash: ['pinhash', 'hash'],
  role: ['role', 'roles'], dateofbirth: ['dateofbirth', 'dob', 'birthdate'], primarymobile: ['primarymobile', 'mobile', 'phone'], stock: ['stock', 'stockqty', 'quantityinstock'],
  feeamount: ['feeamount', 'fee'], chargefee: ['chargefee', 'charged'], allstudents: ['allstudents', 'all'], payableid: ['payableid', 'feeid', 'purchaseid', 'referenceid'],
  persontype: ['persontype', 'type'],
};

type Row = Record<string, string>;
export interface LegacyReport {
  dryRun: boolean;
  counts: Record<string, { imported: number; errors: number }>;
  errors: { sheet: string; row: number; message: string }[];
}
export interface InspectReport {
  sheets: { name: string; rows: number; valid: number; invalid: number; missing: string[]; known: boolean }[];
  missingIdentity: string[];
}

class Rollback extends Error {}

const canonicalSheet = (name: string): string | null => Object.keys(SHEETS).find((s) => s.toLowerCase() === normalizeHeader(name)) ?? null;
const aliasesOf = (column: string) => ALIASES[column] ?? [column];
const value = (row: Row, column: string): string | null => {
  for (const alias of aliasesOf(column)) if (row[alias] !== undefined && row[alias] !== '') return row[alias];
  return null;
};
const required = (row: Row, column: string): string => value(row, column) ?? (() => { throw new Error(`Missing ${column}.`); })();
const truthy = (v: string | null) => !!v && ['1', 'true', 'yes', 'y', 'x', 'on'].includes(v.toLowerCase());
const list = (v: string | null) => (v ?? '').split(/[,;|]/).map((s) => s.trim()).filter(Boolean);
const date = (v: string | null) => (v ? parseFlexibleDate(v) : null);
const dateTime = (v: string | null): Date | null => {
  const d = date(v);
  return d ? new Date(`${d}T00:00:00Z`) : null;
};

/** Read the workbook and count valid rows per sheet, without touching the database. */
export async function inspectLegacy(bytes: Buffer): Promise<InspectReport> {
  const sheets = await readWorkbook(bytes);
  const out: InspectReport['sheets'] = [];
  for (const sheet of sheets) {
    const canonical = canonicalSheet(sheet.name);
    const need = canonical ? SHEETS[canonical] : [];
    const missing = need.filter((c) => !aliasesOf(c).some((a) => sheet.headers.includes(a)));
    let valid = 0;
    for (const r of sheet.rows) if (!missing.length && need.every((c) => value(r, c))) valid++;
    out.push({ name: sheet.name, rows: sheet.rows.length, valid, invalid: sheet.rows.length - valid, missing, known: canonical !== null });
  }
  const present = out.map((s) => canonicalSheet(s.name)).filter(Boolean);
  return { sheets: out, missingIdentity: ['Students', 'Users'].filter((s) => !present.includes(s)) };
}

/**
 * Import the legacy Attendance and Finance workbooks. Sheets run in dependency order, rows
 * update by natural key, and a bad row is reported without stopping the rest. A dry run
 * does everything and then rolls it all back.
 */
export async function importLegacy(bytes: Buffer, opts: { dryRun: boolean; actor?: AuthUser | null; onlySheet?: string | null }): Promise<LegacyReport> {
  const actor = opts.actor ?? null;
  const sheets = new Map<string, ReadSheet>();
  for (const sheet of await readWorkbook(bytes)) {
    const canonical = canonicalSheet(sheet.name);
    if (canonical) sheets.set(canonical, sheet);
  }
  const report: LegacyReport = { dryRun: opts.dryRun, counts: {}, errors: [] };
  const ctx = new Importer(actor, report);
  try {
    await db.transaction(async (tx) => {
      for (const name of Object.keys(SHEETS)) {
        const sheet = sheets.get(name);
        if (!sheet || (opts.onlySheet && opts.onlySheet.toLowerCase() !== name.toLowerCase())) continue;
        await ctx.run(tx, name, sheet.rows);
      }
      await ctx.recalculate(tx);
      if (opts.dryRun) throw new Rollback();
      await recordAudit('import.legacy', null, { counts: report.counts, errors: report.errors.length }, actor?.id ?? null, tx);
    });
  } catch (e) {
    if (!(e instanceof Rollback)) throw e;
  }
  flushSettings();
  return report;
}

class Importer {
  private map: Record<string, Record<string, number>> = {};
  private row = 0;
  constructor(private actor: AuthUser | null, private report: LegacyReport) {}

  async run(tx: Tx, sheet: string, rows: Row[]) {
    this.report.counts[sheet] = { imported: 0, errors: 0 };
    const handler = (this as unknown as Record<string, (tx: Tx, r: Row) => Promise<void>>)[`import${sheet}`].bind(this);
    for (const [i, r] of rows.entries()) {
      this.row = i + 2;
      try {
        await tx.transaction(async (row) => handler(row as unknown as Tx, r));
        this.report.counts[sheet].imported++;
      } catch (e) {
        this.report.counts[sheet].errors++;
        this.report.errors.push({ sheet, row: i + 2, message: e instanceof Error ? e.message : 'Failed' });
      }
    }
  }

  private remember(entity: string, keys: (string | null | undefined)[], id: number) {
    for (const k of keys) if (k) (this.map[entity] ??= {})[String(k).toUpperCase()] = id;
  }

  private async lookup(tx: Tx, entity: string, key: string | null, must = true): Promise<number | null> {
    const k = (key ?? '').trim().toUpperCase();
    if (!k) { if (must) throw new Error(`Missing ${entity} reference.`); return null; }
    let id: number | undefined = this.map[entity]?.[k];
    if (id === undefined) {
      const S = schema;
      const find = async (t: MySqlTable & { id: any; legacyId: any; nationalId?: any }, byNational: boolean) => {
        const [r] = await tx.select({ id: t.id }).from(t).where(byNational ? or(eq(t.nationalId, k), eq(t.legacyId, k)) : eq(t.legacyId, k)).limit(1);
        return (r as { id: number } | undefined)?.id;
      };
      id = entity === 'student' ? await find(S.students, true) : entity === 'user' ? await find(S.users, true) : entity === 'group' ? await find(S.groups as never, false)
        : entity === 'activity' ? await find(S.activities as never, false) : entity === 'class_fee' ? await find(S.classFees as never, false) : entity === 'annual_fee' ? await find(S.annualFees as never, false)
        : entity === 'shop_item' ? await find(S.shopItems as never, false) : entity === 'purchase' ? await find(S.purchases as never, false) : undefined;
    }
    if (id === undefined && must) throw new Error(`Unknown ${entity.replace(/_/g, ' ')} “${k}”.`);
    return id ?? null;
  }

  private section(v: string | null) {
    const s = ScoutSection.fromLoose(v);
    if (s) return s;
    const m = ({ cub: 'Cub Scout', cubs: 'Cub Scout', precub: 'Pre Cub', 'pre-cub': 'Pre Cub', 'pre cubs': 'Pre Cub', rovers: 'Rover', scouts: 'Scout' } as Record<string, string>)[(v ?? '').trim().toLowerCase()];
    if (!m) throw new Error(`Unknown section “${v}”.`);
    return m as (typeof ScoutSection.values)[number];
  }

  private studentStatus(v: string | null): string {
    return StudentStatus.fromLoose(v) ?? (({ verified: 'active', approved: 'active', '': 'active', left: 'inactive', removed: 'inactive', archived: 'inactive' } as Record<string, string>)[(v ?? '').trim().toLowerCase()] ?? 'pending');
  }

  private roles(v: string | null): RoleValue[] {
    const out: RoleValue[] = [];
    for (const item of list(v)) {
      const role = Role.fromLoose(item) ?? (({ scout: 'student', cub: 'student', rover: 'student', guardian: 'parent' } as Record<string, RoleValue>)[item.toLowerCase()] ?? null);
      if (!role) throw new Error(`Unknown role “${item}”. The user was not imported.`);
      out.push(role);
    }
    if (!out.length) throw new Error('No role given. The user was not imported.');
    return [...new Set(out)];
  }

  // ── Sheets ───────────────────────────────────────────────────────────────

  async importStudents(tx: Tx, r: Row) {
    const S = schema.students;
    const nationalId = required(r, 'nationalid').toUpperCase();
    const [existing] = await tx.select().from(S).where(eq(S.nationalId, nationalId)).limit(1);
    const status = this.studentStatus(value(r, 'status'));
    const now = new Date();
    const data = {
      indexNumber: value(r, 'indexnumber') ?? existing?.indexNumber ?? `LEG-${nationalId}`,
      name: required(r, 'name'), email: value(r, 'email') ?? existing?.email ?? null, gender: Gender.fromLoose(value(r, 'gender')),
      section: this.section(value(r, 'section')), status, dateOfBirth: date(value(r, 'dateofbirth')), parentName: value(r, 'parentname'),
      primaryMobile: value(r, 'primarymobile'), secondaryMobile: value(r, 'secondarymobile'), permanentAddress: value(r, 'permanentaddress'), presentAddress: value(r, 'presentaddress'),
      className: value(r, 'classname'), patrol: value(r, 'patrol'), legacyId: value(r, 'id') ?? existing?.legacyId ?? null, deletedAt: null, updatedAt: now,
      verifiedAt: status === 'active' && !existing?.verifiedAt ? now : existing?.verifiedAt ?? null,
    };
    let id: number;
    if (existing) { await tx.update(S).set(data as never).where(eq(S.id, existing.id)); id = existing.id; }
    else [{ id }] = await tx.insert(S).values({ ...data, nationalId, createdAt: now } as never).$returningId();
    this.remember('student', [data.legacyId, nationalId], id);
  }

  async importUsers(tx: Tx, r: Row) {
    const U = schema.users;
    const nationalId = required(r, 'nationalid').toUpperCase();
    const roles = this.roles(value(r, 'role'));
    const [existing] = await tx.select().from(U).where(eq(U.nationalId, nationalId)).limit(1);
    let status: string = UserStatus.fromLoose(value(r, 'status')) ?? 'active';
    const pin = value(r, 'pin');
    const salt = value(r, 'pinsalt');
    const hash = value(r, 'pinhash') ?? (pin && /^[a-f0-9]{64}$/i.test(pin) ? pin : null);
    const now = new Date();
    const auth: Record<string, unknown> = {};
    if (hash) Object.assign(auth, { legacyPinHash: hash.toLowerCase(), legacyPinSalt: salt ?? null, password: await hashPin(randomBytes(20).toString('hex')) });
    else if (pin) Object.assign(auth, { password: await hashPin(pin), legacyPinHash: null, legacyPinSalt: null });
    else if (!existing) {
      auth.password = await hashPin(randomBytes(20).toString('hex'));
      status = 'inactive';
      this.report.errors.push({ sheet: 'Users', row: this.row, message: `${nationalId}: no PIN in the sheet, so the account was imported inactive. Reset the PIN to activate it.` });
    }
    const studentId = (await this.lookup(tx, 'student', value(r, 'student'), false)) ?? (await this.lookup(tx, 'student', nationalId, false));
    let linkedStudent = existing?.studentId ?? null;
    if (studentId) {
      const [taken] = await tx.select({ id: U.id }).from(U).where(and(eq(U.studentId, studentId), existing ? ne(U.id, existing.id) : undefined)).limit(1);
      if (!taken) linkedStudent = studentId;
    }
    const data = { name: required(r, 'name'), email: value(r, 'email') ?? existing?.email ?? null, legacyId: value(r, 'id') ?? existing?.legacyId ?? null, status, verifiedAt: existing?.verifiedAt ?? now, studentId: linkedStudent, deletedAt: null, updatedAt: now, ...auth };
    let id: number;
    if (existing) { await tx.update(U).set(data as never).where(eq(U.id, existing.id)); id = existing.id; }
    else [{ id }] = await tx.insert(U).values({ ...data, nationalId, createdAt: now } as never).$returningId();
    if (linkedStudent && !roles.includes('student')) roles.push('student');
    await tx.delete(schema.userRoles).where(eq(schema.userRoles.userId, id));
    for (const role of roles) await tx.insert(schema.userRoles).values({ userId: id, role, createdAt: now, updatedAt: now });
    this.remember('user', [data.legacyId, nationalId], id);
  }

  async importParentLinks(tx: Tx, r: Row) {
    const L = schema.parentStudentLinks;
    const parentId = (await this.lookup(tx, 'user', required(r, 'parent')))!;
    const studentId = (await this.lookup(tx, 'student', required(r, 'student')))!;
    const status = ParentLinkStatus.fromLoose(value(r, 'status')) ?? 'approved';
    if (status === 'pending' || status === 'approved') {
      const [other] = await tx.select({ id: L.id }).from(L).where(and(eq(L.studentId, studentId), ne(L.parentUserId, parentId), inArray(L.status, ['pending', 'approved']))).limit(1);
      if (other) throw new Error('This scout already has another pending or approved parent.');
    }
    const now = new Date();
    await tx.insert(L).values({ parentUserId: parentId, studentId, status, createdAt: now, updatedAt: now }).onDuplicateKeyUpdate({ set: { status, updatedAt: now } });
    await tx.insert(schema.userRoles).values({ userId: parentId, role: 'parent', createdAt: now, updatedAt: now }).onDuplicateKeyUpdate({ set: { role: 'parent' } });
  }

  async importGroups(tx: Tx, r: Row) {
    const G = schema.groups;
    const legacyId = required(r, 'id');
    const [existing] = await tx.select().from(G).where(eq(G.legacyId, legacyId)).limit(1);
    const data = { name: required(r, 'name'), type: value(r, 'type'), status: RecordStatus.fromLoose(value(r, 'status')) ?? 'Active', deletedAt: null, updatedAt: new Date() };
    let id: number;
    if (existing) { await tx.update(G).set(data as never).where(eq(G.id, existing.id)); id = existing.id; }
    else [{ id }] = await tx.insert(G).values({ ...data, legacyId, createdAt: new Date() } as never).$returningId();
    this.remember('group', [legacyId], id);
  }

  async importGroupMembers(tx: Tx, r: Row) {
    const now = new Date();
    await tx.insert(schema.groupMembers).ignore().values({ groupId: (await this.lookup(tx, 'group', required(r, 'groupid')))!, studentId: (await this.lookup(tx, 'student', required(r, 'student')))!, createdAt: now, updatedAt: now });
  }

  async importGroupLeaders(tx: Tx, r: Row) {
    const groupId = (await this.lookup(tx, 'group', required(r, 'groupid')))!;
    const userId = (await this.lookup(tx, 'user', required(r, 'user')))!;
    const roles = (await tx.select({ role: schema.userRoles.role }).from(schema.userRoles).where(eq(schema.userRoles.userId, userId))).map((x) => x.role);
    if (!roles.includes('leader') && !roles.includes('admin')) throw new Error('That user does not have the leader role.');
    const now = new Date();
    await tx.insert(schema.groupLeaders).ignore().values({ groupId, userId, createdAt: now, updatedAt: now });
  }

  async importGroupAssistantLeaders(tx: Tx, r: Row) {
    const groupId = (await this.lookup(tx, 'group', required(r, 'groupid')))!;
    const studentId = (await this.lookup(tx, 'student', required(r, 'student')))!;
    const [s] = await tx.select({ section: schema.students.section }).from(schema.students).where(eq(schema.students.id, studentId));
    if (s?.section !== 'Rover') throw new Error('Assistant leaders must be Rover scouts.');
    const now = new Date();
    await tx.insert(schema.groupAssistantLeaders).ignore().values({ groupId, studentId, createdAt: now, updatedAt: now });
  }

  async importActivities(tx: Tx, r: Row) {
    const A = schema.activities;
    const legacyId = required(r, 'id');
    const d = date(required(r, 'date'));
    if (!d) throw new Error('The date could not be read.');
    const fee = value(r, 'feeamount');
    const [existing] = await tx.select().from(A).where(eq(A.legacyId, legacyId)).limit(1);
    const data = {
      name: required(r, 'name'), date: d, details: value(r, 'details'), allStudents: truthy(value(r, 'allstudents')),
      chargeFee: truthy(value(r, 'chargefee')) || money.isPositive(fee), feeAmount: money.isPositive(fee) ? money.normalize(fee) : null, deletedAt: null, updatedAt: new Date(),
    };
    let id: number;
    if (existing) { await tx.update(A).set(data as never).where(eq(A.id, existing.id)); id = existing.id; }
    else [{ id }] = await tx.insert(A).values({ ...data, legacyId, createdAt: new Date() } as never).$returningId();
    await tx.delete(schema.activitySections).where(eq(schema.activitySections.activityId, id));
    for (const s of new Set(list(value(r, 'sections')).map((x) => ScoutSection.fromLoose(x)).filter(Boolean) as string[])) await tx.insert(schema.activitySections).values({ activityId: id, section: s });
    await tx.delete(schema.activityGroups).where(eq(schema.activityGroups.activityId, id));
    const groups = new Set<number>();
    for (const g of list(value(r, 'groups'))) { const gid = await this.lookup(tx, 'group', g, false); if (gid) groups.add(gid); }
    for (const groupId of groups) await tx.insert(schema.activityGroups).values({ activityId: id, groupId });
    this.remember('activity', [legacyId], id);
  }

  async importAttendance(tx: Tx, r: Row) {
    const status = AttendanceStatus.fromLoose(required(r, 'status'));
    if (!status) throw new Error('Unknown attendance status.');
    const activityId = (await this.lookup(tx, 'activity', required(r, 'activityid')))!;
    const studentId = (await this.lookup(tx, 'student', required(r, 'student')))!;
    const now = new Date();
    const set = { status, remarks: value(r, 'remarks'), markedAt: dateTime(value(r, 'markedat')) ?? now, markedBy: this.actor?.id ?? null, updatedAt: now };
    await tx.insert(schema.attendanceRecords).values({ activityId, studentId, ...set, createdAt: now }).onDuplicateKeyUpdate({ set });
  }

  async importRoverAttendance(tx: Tx, r: Row) {
    const status = RoverAttendanceStatus.fromLoose(required(r, 'status'));
    if (!status) throw new Error('Unknown Rover attendance status.');
    const activityId = (await this.lookup(tx, 'activity', required(r, 'activityid')))!;
    const studentId = (await this.lookup(tx, 'student', required(r, 'student')))!;
    const req = value(r, 'isrequired');
    const now = new Date();
    const set = { status, isRequired: req === null || truthy(req), markedAt: now, markedBy: this.actor?.id ?? null, updatedAt: now };
    await tx.insert(schema.roverAttendanceRecords).values({ activityId, studentId, ...set, createdAt: now }).onDuplicateKeyUpdate({ set });
  }

  async importClassFeeConfig(tx: Tx, r: Row) {
    const id = (await this.lookup(tx, 'activity', required(r, 'activityid')))!;
    await tx.update(schema.activities).set({ chargeFee: true, feeAmount: money.normalize(required(r, 'amount')), updatedAt: new Date() }).where(eq(schema.activities.id, id));
  }

  async importClassFees(tx: Tx, r: Row) {
    const F = schema.classFees;
    const activityId = (await this.lookup(tx, 'activity', required(r, 'activityid')))!;
    const studentId = (await this.lookup(tx, 'student', required(r, 'student')))!;
    const [activity] = await tx.select({ date: schema.activities.date }).from(schema.activities).where(eq(schema.activities.id, activityId));
    const status = FeeStatus.fromLoose(value(r, 'status')) ?? 'Pending';
    const due = date(value(r, 'duedate')) ?? new Date(new Date(`${activity.date}T00:00:00Z`).getTime() + 14 * 86400_000).toISOString().slice(0, 10);
    const now = new Date();
    const amount = money.normalize(required(r, 'amount'));
    const set = { amount, status: status === 'Void' ? 'Void' : 'Pending', dueDate: due, voidedAt: status === 'Void' ? now : null, legacyId: value(r, 'id'), updatedAt: now };
    const [existing] = await tx.select({ id: F.id }).from(F).where(and(eq(F.activityId, activityId), eq(F.studentId, studentId))).limit(1);
    let id: number;
    if (existing) { await tx.update(F).set(set).where(eq(F.id, existing.id)); id = existing.id; }
    else [{ id }] = await tx.insert(F).values({ activityId, studentId, ...set, outstandingAmount: amount, createdAt: now }).$returningId();
    await recalculate('class_fee', id, tx);
    this.remember('class_fee', [set.legacyId], id);
  }

  async importConfiguration(tx: Tx, r: Row) {
    const key = required(r, 'key').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_');
    const allowed = ['default_class_fee', 'shop_enabled', 'proof_max_kb', 'bank_name', 'account_name', 'account_number', 'payment_instructions', 'footer_text'];
    if (!allowed.includes(key)) throw new Error(`The setting ${key} is not imported.`);
    const v = value(r, 'value');
    const now = new Date();
    await tx.insert(schema.settings).values({ key, value: v, updatedBy: this.actor?.id ?? null, createdAt: now, updatedAt: now }).onDuplicateKeyUpdate({ set: { value: v, updatedAt: now } });
  }

  async importUserPermissions(tx: Tx, r: Row) {
    const permission = Permission.fromLoose(required(r, 'permission'));
    if (!permission) throw new Error('Unknown permission.');
    const userId = (await this.lookup(tx, 'user', required(r, 'user')))!;
    const now = new Date();
    await tx.insert(schema.userPermissions).ignore().values({ userId, permission, createdAt: now, updatedAt: now });
  }

  async importAnnualFeeConfig(tx: Tx, r: Row) {
    const Y = schema.annualFeeYears;
    const year = Number(required(r, 'year'));
    if (!(year >= 2000)) throw new Error('The year must be 2000 or later.');
    const now = new Date();
    const set = { amount: money.normalize(required(r, 'amount')), status: RecordStatus.fromLoose(value(r, 'status')) ?? 'Active', updatedAt: now };
    await tx.insert(Y).values({ year, ...set, createdBy: this.actor?.id ?? null, createdAt: now }).onDuplicateKeyUpdate({ set });
  }

  async importAnnualFees(tx: Tx, r: Row) {
    const F = schema.annualFees;
    const [year] = await tx.select().from(schema.annualFeeYears).where(eq(schema.annualFeeYears.year, Number(required(r, 'year')))).limit(1);
    if (!year) throw new Error('Add this year to AnnualFeeConfig first.');
    const type = PersonType.fromLoose(value(r, 'persontype')) ?? (value(r, 'user') ? 'Leader' : 'Student');
    const leader = type === 'Leader';
    const personId = (await this.lookup(tx, leader ? 'user' : 'student', required(r, leader ? 'user' : 'student')))!;
    let section: string | null = null;
    if (!leader) section = ScoutSection.fromLoose(value(r, 'section')) ?? (await tx.select({ s: schema.students.section }).from(schema.students).where(eq(schema.students.id, personId)))[0]?.s ?? null;
    const now = new Date();
    const amount = money.normalize(required(r, 'amount'));
    const set = { personType: type, section, amount, status: 'Pending', legacyId: value(r, 'id'), updatedAt: now };
    const [existing] = await tx.select({ id: F.id }).from(F).where(and(eq(F.annualFeeYearId, year.id), eq(leader ? F.userId : F.studentId, personId))).limit(1);
    let id: number;
    if (existing) { await tx.update(F).set(set).where(eq(F.id, existing.id)); id = existing.id; }
    else [{ id }] = await tx.insert(F).values({ annualFeeYearId: year.id, ...(leader ? { userId: personId } : { studentId: personId }), ...set, outstandingAmount: amount, createdAt: now }).$returningId();
    await recalculate('annual_fee', id, tx);
    this.remember('annual_fee', [set.legacyId], id);
  }

  async importPayments(tx: Tx, r: Row) {
    const P = schema.payments;
    const legacyId = required(r, 'id');
    const t = required(r, 'type').toLowerCase().replace(/[^a-z]/g, '');
    const type = t.includes('class') ? 'class_fee' : t.includes('annual') ? 'annual_fee' : t.includes('shop') || t.includes('purchase') ? 'purchase' : null;
    if (!type) throw new Error('Unknown payment type.');
    const payableId = (await this.lookup(tx, type, required(r, 'payableid')))!;
    const table = type === 'class_fee' ? schema.classFees : type === 'annual_fee' ? schema.annualFees : schema.purchases;
    const [payable] = await tx.select({ studentId: (table as typeof schema.classFees).studentId }).from(table as typeof schema.classFees).where(eq((table as typeof schema.classFees).id, payableId));
    if (!payable) throw new Error('The record being paid was not found.');
    const raw = (value(r, 'status') ?? '').toLowerCase();
    const status = PaymentStatus.fromLoose(value(r, 'status')) ?? (['approved', 'verified'].includes(raw) ? 'Paid' : 'AwaitingVerification');
    const now = new Date();
    const set = {
      payableType: type, payableId, studentId: payable.studentId, amount: money.normalize(required(r, 'amount')), method: PaymentMethod.fromLoose(value(r, 'method')) ?? 'cash', status,
      submittedAt: dateTime(value(r, 'submittedat')) ?? now, verifiedAt: dateTime(value(r, 'verifiedat')),
      rejectionReason: status === 'Rejected' ? value(r, 'rejectionreason') ?? 'Rejected in the legacy workbook.' : null, updatedAt: now,
    };
    const [existing] = await tx.select({ id: P.id }).from(P).where(eq(P.legacyId, legacyId)).limit(1);
    if (existing) await tx.update(P).set(set).where(eq(P.id, existing.id));
    else await tx.insert(P).values({ ...set, legacyId, createdAt: now });
  }

  async importShopItems(tx: Tx, r: Row) {
    const I = schema.shopItems;
    const legacyId = required(r, 'id');
    const [existing] = await tx.select().from(I).where(eq(I.legacyId, legacyId)).limit(1);
    const data = {
      name: required(r, 'name'), description: value(r, 'description'), price: money.normalize(required(r, 'price')), stockQty: Math.max(0, Number(value(r, 'stock') ?? 0) || 0),
      status: RecordStatus.fromLoose(value(r, 'status')) ?? 'Active', deletedAt: null, updatedAt: new Date(),
    };
    let id: number;
    if (existing) { await tx.update(I).set(data).where(eq(I.id, existing.id)); id = existing.id; }
    else [{ id }] = await tx.insert(I).values({ ...data, legacyId, createdAt: new Date() }).$returningId();
    this.remember('shop_item', [legacyId], id);
  }

  async importPurchases(tx: Tx, r: Row) {
    const Pu = schema.purchases;
    const legacyId = required(r, 'id');
    const itemId = (await this.lookup(tx, 'shop_item', required(r, 'item')))!;
    const [item] = await tx.select().from(schema.shopItems).where(eq(schema.shopItems.id, itemId));
    const quantity = Math.max(1, Number(required(r, 'quantity')) || 1);
    const unit = money.normalize(value(r, 'unitprice') ?? item.price);
    const total = money.normalize(value(r, 'total') ?? money.mul(unit, quantity));
    const purchaseStatus = PurchaseStatus.fromLoose(value(r, 'purchasestatus')) ?? 'PendingPayment';
    const studentId = (await this.lookup(tx, 'student', required(r, 'student')))!;
    const now = new Date();
    const set = {
      studentId, totalAmount: total, outstandingAmount: total, paymentStatus: 'Pending', purchaseStatus,
      // Legacy stock figures already reflect paid orders.
      stockDecremented: ['Confirmed', 'ReadyForCollection', 'Delivered'].includes(purchaseStatus), updatedAt: now,
    };
    const [existing] = await tx.select({ id: Pu.id }).from(Pu).where(eq(Pu.legacyId, legacyId)).limit(1);
    let id: number;
    if (existing) { await tx.update(Pu).set(set).where(eq(Pu.id, existing.id)); id = existing.id; }
    else [{ id }] = await tx.insert(Pu).values({ ...set, legacyId, createdAt: now }).$returningId();
    await tx.delete(schema.purchaseItems).where(eq(schema.purchaseItems.purchaseId, id));
    await tx.insert(schema.purchaseItems).values({ purchaseId: id, shopItemId: itemId, itemNameSnapshot: item.name, quantity, unitPrice: unit, totalAmount: total, createdAt: now, updatedAt: now });
    this.remember('purchase', [legacyId], id);
  }

  async recalculate(tx: Tx) {
    for (const type of ['class_fee', 'annual_fee', 'purchase'] as const) {
      for (const id of new Set(Object.values(this.map[type] ?? {}))) {
        if (type === 'purchase') {
          const [p] = await tx.select({ s: schema.purchases.purchaseStatus }).from(schema.purchases).where(eq(schema.purchases.id, id));
          if (p?.s === 'Cancelled') continue;
        }
        await recalculate(type, id, tx);
      }
    }
  }
}


/** A blank workbook with every sheet and its required columns, with dropdowns for coded values. */
export function legacyTemplate(): Promise<Buffer> {
  const extra: Record<string, string[]> = {
    Students: ['Index Number', 'Email', 'Gender', 'Section', 'Status', 'Date of Birth', 'Parent Name', 'Primary Mobile'],
    Users: ['Role', 'Email', 'Status', 'PIN', 'Student'],
    ParentLinks: ['Status'], Groups: ['Type', 'Status'], Activities: ['Details', 'All Students', 'Charge Fee', 'Fee Amount', 'Sections', 'Groups'],
    Attendance: ['Remarks'], RoverAttendance: ['Is Required'], ClassFees: ['Status', 'Due Date', 'ID'], AnnualFees: ['Person Type', 'User', 'Student', 'Section', 'ID'],
    Payments: ['Payable ID', 'Method', 'Status', 'Submitted At', 'Verified At'], ShopItems: ['Stock', 'Description', 'Status'], Purchases: ['Unit Price', 'Purchase Status'],
  };
  const dropdowns: Record<string, Dropdowns> = {
    Students: { Gender: Gender.values, Section: ScoutSection.values, Status: StudentStatus.values },
    Users: { Role: Role.values, Status: UserStatus.values },
    ParentLinks: { Status: ParentLinkStatus.values }, Groups: { Status: RecordStatus.values }, Attendance: {}, RoverAttendance: {},
    ClassFees: { Status: FeeStatus.values }, AnnualFees: { 'Person Type': PersonType.values, Section: ScoutSection.values },
    Payments: { Method: PaymentMethod.values, Status: PaymentStatus.values }, ShopItems: { Status: RecordStatus.values }, Purchases: { 'Purchase Status': PurchaseStatus.values },
  };
  const pretty = (c: string) => c.replace(/(id|name|date)$/i, ' $1').replace(/^./, (x) => x.toUpperCase()).replace(/ +/g, ' ').trim();
  return buildWorkbook(Object.entries(SHEETS).map(([name, need]) => {
    const headings = [...new Set([...need.map(pretty), ...(extra[name] ?? [])])];
    return { name, headings, rows: [], dropdowns: dropdowns[name], spareRows: 500 };
  }));
}
