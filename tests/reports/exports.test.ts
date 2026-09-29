import ExcelJS from 'exceljs';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { exportLedger } from '@/server/ledger-export';
import { HEADINGS, REPORT_DROPDOWNS, reportPage, reportRows, reportTotals, totalsRow } from '@/server/reports';
import { importTemplate, processStudentImport } from '@/server/student-import';
import { buildWorkbook, readWorkbook } from '@/server/xlsx';
import { loadPayableOrThrow } from '@/server/payables';
import { submitPayment } from '@/server/payments';
import { makeActivity, makeAdmin, makeGroup, makeLeader, makeStudent } from '../factories';

async function load(bytes: Buffer) {
  const wb = new ExcelJS.Workbook();
  await wb.xlsx.load(bytes as never);
  return wb;
}

describe('workbooks with dropdowns', () => {
  it('adds list validation from a hidden Lists sheet, including spare rows', async () => {
    const bytes = await buildWorkbook([{ name: 'Test', headings: ['name', 'status'], rows: [['A', 'active']], dropdowns: { status: ['active', 'inactive'] }, spareRows: 5 }]);
    const wb = await load(bytes);
    expect(wb.getWorksheet('Lists')?.state).toBe('hidden');
    const sheet = wb.getWorksheet('Test')!;
    expect(sheet.getCell('B2').dataValidation).toMatchObject({ type: 'list', formulae: ['Lists!$A$1:$A$2'] });
    expect(sheet.getCell('B7').dataValidation?.type).toBe('list');
    expect(sheet.getCell('A2').dataValidation?.type).toBeUndefined();
    expect(wb.getWorksheet('Lists')?.getCell('A2').value).toBe('inactive');
  });

  it('reads headers case- and punctuation-insensitively and skips blank rows', async () => {
    const bytes = await buildWorkbook([{ name: 'S', headings: ['Student ID', 'Full Name'], rows: [['1', 'A'], [null, null], ['2', 'B']] }]);
    const [sheet] = await readWorkbook(bytes);
    expect(sheet.headers).toEqual(['studentid', 'fullname']);
    expect(sheet.rows).toEqual([{ studentid: '1', fullname: 'A' }, { studentid: '2', fullname: 'B' }]);
  });

  it('csv output has no Lists sheet', async () => {
    const bytes = await buildWorkbook([{ name: 'T', headings: ['a'], rows: [['x']], dropdowns: { a: ['x'] } }], 'csv');
    expect(bytes.toString()).toContain('a\nx');
  });

  it('every ledger export opens and has its status dropdown', async () => {
    await makeStudent();
    const wb = await load(await exportLedger('students'));
    const sheet = wb.getWorksheet('students')!;
    const statusCol = sheet.getRow(1).values as string[];
    const col = statusCol.indexOf('status');
    expect(sheet.getCell(2, col).dataValidation?.type).toBe('list');
    expect(sheet.getCell(2, col + 0).value).toBe('active');
  });
});

describe('reports', () => {
  it('lists rows with labels and totals, scoped to the leader\'s scouts', async () => {
    const admin = await makeAdmin();
    const leader = await makeLeader();
    const mine = await makeStudent({ name: 'Mine' });
    const theirs = await makeStudent({ name: 'Theirs' });
    await makeGroup({ leaders: [leader], members: [mine] });
    const activity = await makeActivity({ all: true, charge: true, fee: '30.00' });
    const now = new Date();
    for (const s of [mine, theirs]) {
      await db.insert(schema.classFees).values({ activityId: activity.id, studentId: s.id, amount: '30.00', outstandingAmount: '30.00', status: 'Pending', createdAt: now, updatedAt: now });
    }
    const fees = await db.select().from(schema.classFees);
    await submitPayment(await loadPayableOrThrow('class_fee', { id: fees.find((f) => f.studentId === mine.id)!.id }), admin, '30.00', 'cash');

    const adminRows = await reportRows('class-fees', {}, admin);
    expect(adminRows).toHaveLength(2);
    const leaderRows = await reportRows('class-fees', {}, leader);
    expect(leaderRows).toEqual([[activity.name, 'Mine', '30.00', '30.00', '0.00', 'Paid']]);

    const totals = await reportTotals('class-fees', {}, admin);
    expect(totals).toMatchObject({ rows: 2, billed: '60.00', paid: '30.00', outstanding: '30.00' });
    expect(totalsRow('class-fees', totals)).toEqual(['Total (2 rows)', '', '60.00', '30.00', '30.00', '']);
    expect((await reportRows('class-fees', { status: 'Paid' }, admin))).toHaveLength(1);
    expect((await reportRows('class-fees', { q: 'Theirs' }, admin))).toHaveLength(1);

    const page = await reportPage('payments', {}, admin, 1);
    expect(page.rows[0][3]).toBe('30.00');
    expect(page.rows[0][4]).toBe('Cash');
  });

  it('every report has matching headings, dropdown headings and a runnable query', async () => {
    const admin = await makeAdmin();
    for (const type of Object.keys(HEADINGS) as (keyof typeof HEADINGS)[]) {
      for (const heading of Object.keys(REPORT_DROPDOWNS[type])) expect(HEADINGS[type]).toContain(heading);
      const rows = await reportRows(type, {}, admin);
      for (const row of rows) expect(row).toHaveLength(HEADINGS[type].length);
      expect((await reportTotals(type, {}, admin)).rows).toBe(rows.length);
    }
  });
});

describe('student import', () => {
  const row = (o: Record<string, string> = {}) => ({
    name: 'Aishath Example', national_id: 'a123456', email: 'aishath@example.com', gender: 'female', section: 'cub scout', index_number: 'IX1001', permanent_address: 'Male', present_address: 'Male',
    date_of_birth: '15.03.2015', parent_name: 'Ibrahim', primary_mobile: '7771234', secondary_mobile: '', class_name: '', patrol: '', status: '', pin: '', ...o,
  });
  const file = (rows: Record<string, string>[], headings = Object.keys(rows[0])) => buildWorkbook([{ name: 'Students', headings, rows: rows.map((r) => headings.map((h) => r[h] ?? '')) }]);

  it('the template has dropdowns for gender, section and status', async () => {
    const sheet = (await load(await importTemplate())).getWorksheet('Students')!;
    const headings = sheet.getRow(1).values as string[];
    for (const h of ['gender', 'section', 'status']) expect(sheet.getCell(2, headings.indexOf(h)).dataValidation?.type).toBe('list');
  });

  it('checking saves nothing; importing enrols, normalises values and skips existing ids', async () => {
    const admin = await makeAdmin();
    const bytes = await file([row(), row({ national_id: 'A200000', email: 'b@example.com', index_number: 'IX1002', name: 'Bad Date', date_of_birth: 'nonsense' })]);
    const check = await processStudentImport(bytes, null);
    expect(check.lines.map((l) => l.result)).toEqual(['ready', 'error']);
    expect(await db.select().from(schema.students)).toHaveLength(0);

    const done = await processStudentImport(bytes, admin);
    expect(done).toMatchObject({ created: 1, errors: 1 });
    const [student] = await db.select().from(schema.students);
    expect(student).toMatchObject({ nationalId: 'A123456', gender: 'Female', section: 'Cub Scout', dateOfBirth: '2015-03-15', status: 'active' });

    const again = await processStudentImport(bytes, admin);
    expect(again.lines[0]).toMatchObject({ result: 'exists' });
    expect(await db.select().from(schema.students)).toHaveLength(1);
  });

  it('reports duplicates inside the file and missing columns', async () => {
    const admin = await makeAdmin();
    const dup = await processStudentImport(await file([row(), row({ national_id: 'A999999', index_number: 'IX2' })]), admin);
    expect(dup.lines.map((l) => l.message)).toEqual(['This email appears more than once in the file.', 'This email appears more than once in the file.']);
    const missing = await processStudentImport(await file([{ name: 'x' }]), admin);
    expect(missing.missing).toEqual(['national_id', 'email']);
  });
});
