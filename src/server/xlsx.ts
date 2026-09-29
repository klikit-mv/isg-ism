import ExcelJS from 'exceljs';

/** Allowed values per column heading; each becomes a dropdown in the sheet. */
export type Dropdowns = Record<string, readonly string[]>;

export interface SheetSpec {
  name: string;
  /** Rows above the headings (title, who generated it). */
  preface?: (string | number | null)[][];
  headings: string[];
  rows: (string | number | boolean | Date | null)[][];
  dropdowns?: Dropdowns;
  /** Dropdowns also cover this many blank rows below the data, so new rows can be typed in. */
  spareRows?: number;
}

const safeName = (name: string) => name.replace(/[\\/?*[\]:]/g, ' ').slice(0, 28) || 'Sheet';

/**
 * Build a workbook. Dropdown lists live on a hidden "Lists" sheet and are referenced by
 * range, so they work in Excel, LibreOffice and Google Sheets whatever their length.
 */
export async function buildWorkbook(sheets: SheetSpec[], format: 'xlsx' | 'csv' = 'xlsx'): Promise<Buffer> {
  const workbook = new ExcelJS.Workbook();
  workbook.creator = 'Scout Management System';
  const lists = format === 'xlsx' && sheets.some((s) => s.dropdowns && Object.keys(s.dropdowns).length) ? workbook.addWorksheet('Lists', { state: 'hidden' }) : null;
  let listColumn = 0;
  const listRanges = new Map<string, string>();

  for (const spec of sheets) {
    const sheet = workbook.addWorksheet(safeName(spec.name));
    for (const row of spec.preface ?? []) sheet.addRow(row);
    const headingRow = (spec.preface?.length ?? 0) + 1;
    const heading = sheet.addRow(spec.headings);
    heading.font = { bold: true };
    heading.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEDE9FE' } };
    for (const row of spec.rows) sheet.addRow(row);
    spec.headings.forEach((h, i) => { sheet.getColumn(i + 1).width = Math.min(40, Math.max(12, h.length + 4)); });
    sheet.views = [{ state: 'frozen', ySplit: headingRow }];

    if (lists) {
      const last = headingRow + Math.max(spec.rows.length, 0) + (spec.spareRows ?? 500);
      spec.headings.forEach((h, i) => {
        const values = spec.dropdowns?.[h];
        if (!values?.length) return;
        const key = `${h}\u0000${values.join('\u0001')}`;
        let range = listRanges.get(key);
        if (!range) {
          listColumn++;
          values.forEach((v, r) => { lists.getCell(r + 1, listColumn).value = v; });
          const letter = lists.getColumn(listColumn).letter;
          range = `Lists!$${letter}$1:$${letter}$${values.length}`;
          listRanges.set(key, range);
        }
        for (let r = headingRow + 1; r <= last; r++) {
          sheet.getCell(r, i + 1).dataValidation = {
            type: 'list', allowBlank: true, formulae: [range], showErrorMessage: true, errorStyle: 'warning',
            errorTitle: 'Not in the list', error: `Choose a value from the ${h} list.`,
          };
        }
      });
    }
  }

  const out = format === 'csv' ? await workbook.csv.writeBuffer() : await workbook.xlsx.writeBuffer();
  return Buffer.from(out as ArrayBuffer);
}

// ── Reading ────────────────────────────────────────────────────────────────

/** "Student ID", "student_id" and "STUDENT-ID" are the same header. */
export const normalizeHeader = (value: unknown): string => String(value ?? '').toLowerCase().replace(/[^a-z0-9]/g, '');

export interface ReadSheet { name: string; headers: string[]; rows: Record<string, string>[] }

function cellText(value: ExcelJS.CellValue): string {
  if (value === null || value === undefined) return '';
  if (value instanceof Date) return value.toISOString().slice(0, 10);
  if (typeof value === 'object') {
    if ('result' in value && value.result !== undefined) return cellText(value.result as ExcelJS.CellValue);
    if ('text' in value) return String(value.text ?? '').trim();
    if ('richText' in value) return value.richText.map((t) => t.text).join('').trim();
    if ('hyperlink' in value) return String((value as { text?: string }).text ?? value.hyperlink).trim();
  }
  return String(value).trim();
}

/** Read every sheet: the first non-empty row is the header, the rest are data keyed by normalised header. */
export async function readWorkbook(bytes: Buffer | Uint8Array, format: 'xlsx' | 'csv' = 'xlsx'): Promise<ReadSheet[]> {
  const workbook = new ExcelJS.Workbook();
  if (format === 'csv') {
    const { Readable } = await import('node:stream');
    await workbook.csv.read(Readable.from(Buffer.from(bytes)));
  } else {
    await workbook.xlsx.load(Buffer.from(bytes) as never);
  }
  const out: ReadSheet[] = [];
  for (const sheet of workbook.worksheets) {
    if (sheet.state !== 'visible' && sheet.name === 'Lists') continue;
    const table: string[][] = [];
    sheet.eachRow({ includeEmpty: false }, (row) => {
      const cells: string[] = [];
      row.eachCell({ includeEmpty: true }, (cell, col) => { cells[col - 1] = cellText(cell.value); });
      table.push(Array.from(cells, (c) => c ?? ''));
    });
    const headerIndex = table.findIndex((r) => r.some((c) => c !== ''));
    if (headerIndex === -1) continue;
    const headers = table[headerIndex].map(normalizeHeader);
    const rows = table.slice(headerIndex + 1)
      .filter((r) => r.some((c) => c !== ''))
      .map((r) => Object.fromEntries(headers.map((h, i) => [h, r[i] ?? '']).filter(([h]) => h !== '')));
    out.push({ name: sheet.name, headers: headers.filter(Boolean), rows });
  }
  return out;
}
