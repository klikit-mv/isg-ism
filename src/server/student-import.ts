import { ScoutSection, Gender, StudentStatus } from '@/lib/enums';
import { parseFlexibleDate } from '@/lib/dates';
import { recordAudit } from './audit';
import { ValidationError } from './errors';
import { createStudent, prepareStudentInput, studentRules } from './students';
import type { AuthUser } from './users';
import { validate } from '@/lib/validate';
import { buildWorkbook, normalizeHeader, readWorkbook } from './xlsx';

export const HEADERS = [
  'name', 'national_id', 'email', 'gender', 'section', 'index_number', 'permanent_address', 'present_address',
  'date_of_birth', 'parent_name', 'primary_mobile', 'secondary_mobile', 'class_name', 'patrol', 'status', 'pin',
] as const;
export const REQUIRED = ['name', 'national_id', 'email'] as const;

/** The blank workbook people fill in, with dropdowns for gender, section and status. */
export function importTemplate(): Promise<Buffer> {
  return buildWorkbook([{
    name: 'Students',
    headings: [...HEADERS],
    rows: [['Aishath Example', 'A123456', 'aishath@example.com', 'Female', 'Cub Scout', 'IX1001', 'Henveiru, Male', 'Henveiru, Male', '15.03.2015', 'Ibrahim Example', '7771234', '', 'Grade 4', 'Eagle', 'active', '']],
    dropdowns: { gender: Gender.values, section: ScoutSection.values, status: StudentStatus.values },
    spareRows: 500,
  }]);
}

export interface ImportLine { row: number; name: string; national_id: string; result: 'ready' | 'created' | 'exists' | 'error'; message: string }
export interface ImportReport { lines: ImportLine[]; created: number; skipped: number; errors: number; missing: string[] }

const blank = (v: unknown) => (v === '' || v === undefined ? null : v);

function normalizeRow(raw: Record<string, string>): Record<string, unknown> {
  const data: Record<string, unknown> = {};
  for (const h of HEADERS) data[h] = blank(raw[normalizeHeader(h)]);
  data.national_id = data.national_id ? String(data.national_id).trim().toUpperCase() : null;
  data.gender = Gender.fromLoose(data.gender as string) ?? data.gender;
  data.section = ScoutSection.fromLoose(data.section as string) ?? data.section;
  data.status = StudentStatus.fromLoose(data.status as string) ?? (data.status ?? 'active');
  data.date_of_birth = parseFlexibleDate(data.date_of_birth as string) ?? data.date_of_birth;
  return data;
}

/**
 * Check the workbook (`actor` null) or import it. Existing National IDs are skipped, rows with
 * problems are reported, and a problem in one row never stops the others.
 */
export async function processStudentImport(bytes: Buffer, actor: AuthUser | null, format: 'xlsx' | 'csv' = 'xlsx'): Promise<ImportReport> {
  const [sheet] = await readWorkbook(bytes, format);
  const report: ImportReport = { lines: [], created: 0, skipped: 0, errors: 0, missing: [] };
  if (!sheet) { report.missing = [...REQUIRED]; return report; }
  report.missing = REQUIRED.filter((h) => !sheet.headers.includes(normalizeHeader(h)));
  if (report.missing.length) return report;

  const count = (key: string, rows: Record<string, unknown>[]) => rows.reduce<Record<string, number>>((acc, r) => { const v = String(r[key] ?? '').toLowerCase(); if (v) acc[v] = (acc[v] ?? 0) + 1; return acc; }, {});
  const rows = sheet.rows.map(normalizeRow);
  const emails = count('email', rows);
  const ids = count('national_id', rows);
  const indexes = count('index_number', rows);

  for (const [i, data] of rows.entries()) {
    const line = { row: i + 2, name: String(data.name ?? ''), national_id: String(data.national_id ?? '') };
    const push = (result: ImportLine['result'], message: string) => report.lines.push({ ...line, result, message });
    if ((emails[String(data.email ?? '').toLowerCase()] ?? 0) > 1) { push('error', 'This email appears more than once in the file.'); report.errors++; continue; }
    if ((ids[String(data.national_id ?? '').toLowerCase()] ?? 0) > 1) { push('error', 'This National ID appears more than once in the file.'); report.errors++; continue; }
    if (data.index_number && (indexes[String(data.index_number).toLowerCase()] ?? 0) > 1) { push('error', 'This index number appears more than once in the file.'); report.errors++; continue; }
    try {
      const clean = await validate(prepareStudentInput(data), await studentRules());
      if (actor) {
        await createStudent(clean, actor);
        report.created++;
        push('created', 'Enrolled.');
      } else {
        push('ready', 'Ready to import.');
      }
    } catch (e) {
      if (e instanceof ValidationError) {
        const exists = e.fields.national_id?.toLowerCase().includes('already');
        if (exists) { push('exists', 'A scout or user with this National ID already exists.'); report.skipped++; }
        else { push('error', Object.values(e.fields).join(' ')); report.errors++; }
      } else {
        push('error', e instanceof Error ? e.message : 'Failed'); report.errors++;
      }
    }
  }
  if (actor) await recordAudit('student.imported', null, { created: report.created, skipped: report.skipped, errors: report.errors }, actor.id);
  return report;
}
