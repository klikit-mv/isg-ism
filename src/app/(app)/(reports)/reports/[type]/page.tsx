import Link from 'next/link';
import { notFound } from 'next/navigation';
import { desc } from 'drizzle-orm';
import { db, schema } from '@/db';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Stat } from '@/components/Stat';
import { Table } from '@/components/Table';
import { PaymentMethod } from '@/lib/enums';
import { formatMoney } from '@/lib/money';
import { CATALOG, HEADINGS, cleanFilters, isReportType, reportPage, reportRows, reportTotals, totalsRow } from '@/server/reports';
import { requireStaff } from '@/server/session';

export async function generateMetadata({ params }: { params: Promise<{ type: string }> }) {
  const { type } = await params;
  return { title: isReportType(type) ? `${CATALOG[type].title} report` : 'Report' };
}

export default async function ReportPage({ params, searchParams }: { params: Promise<{ type: string }>; searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireStaff();
  const { type } = await params;
  if (!isReportType(type)) notFound();
  const sp = await searchParams;
  const meta = CATALOG[type];
  const filters = cleanFilters(type, sp);
  const print = sp.print === '1';
  const totals = await reportTotals(type, filters, user);
  const years = meta.filters.includes('year') ? (await db.select({ y: schema.annualFeeYears.year }).from(schema.annualFeeYears).orderBy(desc(schema.annualFeeYears.year))).map((r) => String(r.y)) : [];
  const headings = HEADINGS[type];
  const qs = new URLSearchParams(Object.entries(filters) as [string, string][]).toString();

  if (print) {
    const rows = await reportRows(type, filters, user, 5000);
    return (
      <div className="bg-white p-4 text-gray-900">
        <h1 className="text-xl font-bold">{meta.title} report</h1>
        <p className="mb-3 text-xs text-gray-500">{totals.rows} rows</p>
        <table className="w-full border-collapse text-xs">
          <thead><tr>{headings.map((h) => <th key={h} className="border border-gray-300 bg-gray-100 px-2 py-1 text-left">{h}</th>)}</tr></thead>
          <tbody>
            {rows.map((r, i) => <tr key={i}>{r.map((c, j) => <td key={j} className="border border-gray-300 px-2 py-1">{c}</td>)}</tr>)}
            <tr className="font-bold">{totalsRow(type, totals).map((c, j) => <td key={j} className="border border-gray-300 px-2 py-1">{c}</td>)}</tr>
          </tbody>
        </table>
        <script dangerouslySetInnerHTML={{ __html: 'window.print()' }} />
      </div>
    );
  }

  const page = await reportPage(type, filters, user, currentPage(sp.page));
  return (
    <>
      <PageHeader title={`${meta.title} report`} description={meta.description}>
        <Link href="/reports" className="btn-secondary btn-sm">All reports</Link>
        <a href={`/reports/${type}?${qs}${qs ? '&' : ''}print=1`} target="_blank" rel="noopener" className="btn-secondary btn-sm">Print</a>
        <a href={`/reports/${type}/export?${qs}`} className="btn-accent btn-sm">Download Excel</a>
        <a href={`/reports/${type}/export?${qs}${qs ? '&' : ''}format=csv`} className="btn-secondary btn-sm">Download CSV</a>
      </PageHeader>
      <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Stat label="Rows" value={totals.rows} />
        {totals.billed !== undefined && <Stat label="Billed" value={formatMoney(totals.billed)} />}
        {totals.paid !== undefined && <Stat label="Paid" value={formatMoney(totals.paid)} tone="gold" />}
        {totals.outstanding !== undefined && <Stat label="Outstanding" value={formatMoney(totals.outstanding)} />}
        {totals.amount !== undefined && <Stat label="Amount" value={formatMoney(totals.amount)} />}
      </div>
      <Filters action={`/reports/${type}`}>
        {meta.filters.includes('q') && <FilterInput name="q" label="Search" value={filters.q} />}
        {meta.filters.includes('status') && <FilterSelect name="status" label="Status" value={filters.status} options={meta.statuses} placeholder="Any status" />}
        {meta.filters.includes('method') && <FilterSelect name="method" label="Method" value={filters.method} options={PaymentMethod.options()} placeholder="Any method" />}
        {meta.filters.includes('year') && <FilterSelect name="year" label="Year" value={filters.year} options={years.map((y) => ({ value: y, label: y }))} placeholder="Any year" />}
        {meta.filters.includes('from') && <FilterInput name="from" label="From" type="date" value={filters.from} />}
        {meta.filters.includes('to') && <FilterInput name="to" label="To" type="date" value={filters.to} />}
      </Filters>
      {page.rows.length === 0 ? <Empty message="No rows match these filters." /> : (
        <>
          <Table headers={headings}>
            {page.rows.map((r, i) => <tr key={i}>{r.map((c, j) => <td key={j} data-label={headings[j]}>{c}</td>)}</tr>)}
          </Table>
          <Pagination page={page.page} pages={page.pages} path={`/reports/${type}`} query={filters as Record<string, string | undefined>} />
        </>
      )}
    </>
  );
}
