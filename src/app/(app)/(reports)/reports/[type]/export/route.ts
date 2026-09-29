import { NextResponse } from 'next/server';
import { formatDateTime } from '@/lib/dates';
import { config } from '@/lib/config';
import { recordAudit } from '@/server/audit';
import { CATALOG, HEADINGS, REPORT_DROPDOWNS, cleanFilters, isReportType, reportRows, reportTotals, totalsRow } from '@/server/reports';
import { getUser } from '@/server/session';
import { isActive, isStaff } from '@/server/users';
import { buildWorkbook } from '@/server/xlsx';

export async function GET(request: Request, { params }: { params: Promise<{ type: string }> }) {
  const user = await getUser();
  if (!user) return new NextResponse('Unauthorized', { status: 401 });
  if (!isActive(user) || !isStaff(user)) return new NextResponse('Forbidden', { status: 403 });
  const { type } = await params;
  if (!isReportType(type)) return new NextResponse('Not found', { status: 404 });
  const url = new URL(request.url);
  const format = url.searchParams.get('format') === 'csv' ? 'csv' : 'xlsx';
  const filters = cleanFilters(type, Object.fromEntries(url.searchParams));
  const [rows, totals] = await Promise.all([reportRows(type, filters, user), reportTotals(type, filters, user)]);
  const bytes = await buildWorkbook([{
    name: `${CATALOG[type].title}`,
    preface: [[config.organisation], [`${CATALOG[type].title} report`], [`Generated ${formatDateTime(new Date())} by ${user.name}`], []],
    headings: HEADINGS[type],
    rows: [...rows, totalsRow(type, totals)],
    dropdowns: REPORT_DROPDOWNS[type],
    spareRows: 0,
  }], format);
  await recordAudit('report.exported', null, { type, format, rows: rows.length }, user.id);
  const stamp = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  return new NextResponse(new Uint8Array(bytes), {
    headers: {
      'Content-Type': format === 'csv' ? 'text/csv; charset=utf-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'Content-Disposition': `attachment; filename="${type}-report-${stamp}.${format}"`,
    },
  });
}
