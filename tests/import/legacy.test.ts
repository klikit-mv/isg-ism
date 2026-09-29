import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { authenticate } from '@/server/auth';
import { SHEETS, importLegacy, inspectLegacy, legacyTemplate } from '@/server/legacy-import';
import { buildWorkbook } from '@/server/xlsx';
import { makeAdmin } from '../factories';
import { createHash } from 'node:crypto';

const sheet = (name: string, headings: string[], rows: string[][]) => ({ name, headings, rows });
const workbook = () => buildWorkbook([
  sheet('Students', ['Name', 'National ID', 'Section', 'Gender', 'DOB', 'Status'], [['Aishath', 'a1', 'cubs', 'female', '15.03.2015', 'Verified'], ['Ali', 'a2', 'Scout', 'male', '2010-01-02', '']]),
  sheet('Users', ['Name', 'National ID', 'Role', 'PIN Hash', 'PIN Salt'], [
    ['Leader One', 'l1', 'leader', createHash('sha256').update('salt1' + '4321').digest('hex'), 'salt1'],
    ['Parent One', 'p1', 'parent', '', ''],
    ['Bad Role', 'x1', 'wizard', '', ''],
  ]),
  sheet('ParentLinks', ['Parent', 'Student'], [['p1', 'a1']]),
  sheet('Groups', ['ID', 'Name'], [['G1', 'Eagles']]),
  sheet('GroupMembers', ['Group ID', 'Student'], [['G1', 'a1'], ['G1', 'a2']]),
  sheet('GroupLeaders', ['Group ID', 'User'], [['G1', 'l1'], ['G1', 'p1']]),
  sheet('Activities', ['ID', 'Name', 'Date', 'Fee', 'Sections'], [['ACT1', 'Camp', '01.03.2026', '30', 'Scout']]),
  sheet('Attendance', ['Activity ID', 'Student', 'Status'], [['ACT1', 'a2', 'present'], ['ACT1', 'a1', 'nonsense']]),
  sheet('ClassFees', ['Activity ID', 'Student', 'Amount', 'ID'], [['ACT1', 'a2', '30', 'CF1']]),
  sheet('Payments', ['ID', 'Type', 'Amount', 'Payable ID', 'Status', 'Method'], [['PAY1', 'Class fee', '30', 'CF1', 'approved', 'cash']]),
  sheet('Mystery', ['A'], [['1']]),
]);

describe('legacy workbook import', () => {
  it('inspects sheets without touching the database', async () => {
    const report = await inspectLegacy(await workbook());
    expect(report.sheets.find((s) => s.name === 'Students')).toMatchObject({ rows: 2, valid: 2, known: true });
    expect(report.sheets.find((s) => s.name === 'Mystery')?.known).toBe(false);
    expect(report.missingIdentity).toEqual([]);
    expect(await db.select().from(schema.students)).toHaveLength(0);
  });

  it('a dry run reports results and rolls everything back', async () => {
    const admin = await makeAdmin();
    const report = await importLegacy(await workbook(), { dryRun: true, actor: admin });
    expect(report.counts.Students).toEqual({ imported: 2, errors: 0 });
    expect(await db.select().from(schema.students)).toHaveLength(0);
    expect(await db.select().from(schema.groups)).toHaveLength(0);
  });

  it('imports in dependency order, reports bad rows and keeps the good ones', async () => {
    const admin = await makeAdmin();
    const report = await importLegacy(await workbook(), { dryRun: false, actor: admin });
    expect(report.counts.Users).toEqual({ imported: 2, errors: 1 });
    expect(report.errors.find((e) => e.sheet === 'Users' && e.message.includes('wizard'))).toBeTruthy();
    expect(report.errors.find((e) => e.sheet === 'GroupLeaders')?.message).toContain('leader role');
    expect(report.errors.find((e) => e.sheet === 'Attendance')?.message).toBe('Unknown attendance status.');

    const students = await db.select().from(schema.students);
    expect(students.find((s) => s.nationalId === 'A1')).toMatchObject({ section: 'Cub Scout', gender: 'Female', dateOfBirth: '2015-03-15', status: 'active' });
    const [group] = await db.select().from(schema.groups);
    expect(group.legacyId).toBe('G1');
    expect(await db.select().from(schema.groupMembers)).toHaveLength(2);
    expect(await db.select().from(schema.groupLeaders)).toHaveLength(1);
    expect((await db.select().from(schema.parentStudentLinks))[0].status).toBe('approved');
    expect(await db.select().from(schema.attendanceRecords)).toHaveLength(1);

    const [fee] = await db.select().from(schema.classFees);
    expect(fee).toMatchObject({ amount: '30.00', paidAmount: '30.00', outstandingAmount: '0.00', status: 'Paid' });
    const [parent] = await db.select().from(schema.users).where(eq(schema.users.nationalId, 'P1'));
    expect(parent.status).toBe('inactive');
  });

  it('a legacy leader can sign in with the old PIN hash and salt', async () => {
    const admin = await makeAdmin();
    await importLegacy(await workbook(), { dryRun: false, actor: admin });
    const result = await authenticate('L1', '4321', '127.0.0.1');
    expect(result.nationalId).toBe('L1');
  });

  it('running it again updates instead of duplicating', async () => {
    const admin = await makeAdmin();
    await importLegacy(await workbook(), { dryRun: false, actor: admin });
    await importLegacy(await workbook(), { dryRun: false, actor: admin });
    expect(await db.select().from(schema.students)).toHaveLength(2);
    expect(await db.select().from(schema.groups)).toHaveLength(1);
    expect(await db.select().from(schema.payments)).toHaveLength(1);
    expect(await db.select().from(schema.classFees)).toHaveLength(1);
  });

  it('the template has every sheet with headings the importer accepts', async () => {
    const report = await inspectLegacy(await legacyTemplate());
    expect(report.sheets.map((s) => s.name)).toEqual(Object.keys(SHEETS));
    for (const sheet of report.sheets) expect(sheet.missing).toEqual([]);
  });
});
