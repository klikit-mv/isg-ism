import { and, asc, inArray, isNull } from 'drizzle-orm';
import { db, schema } from '@/db';
import { Badge } from './Badge';
import { FilterInput, FilterSelect, Filters } from './Filters';
import { currentPage, Pagination } from './Pagination';
import { Empty, PageHeader } from './PageHeader';
import { Table } from './Table';
import { AttendanceStatus } from '@/lib/enums';
import { formatDate } from '@/lib/dates';
import { attendanceHistory } from '@/server/attendance-queries';

/** Attendance records for a set of scouts, with text, child, status and date filters. */
export async function AttendanceHistoryPage({ title, path, studentIds, withChildFilter, searchParams }: { title: string; path: string; studentIds: number[]; withChildFilter: boolean; searchParams: Record<string, string | undefined> }) {
  const sp = searchParams;
  const data = await attendanceHistory(studentIds, { q: sp.q, child: sp.child, status: sp.status, from: sp.from, to: sp.to, page: currentPage(sp.page) });
  const children = withChildFilter && studentIds.length
    ? await db.select({ uuid: schema.students.uuid, name: schema.students.name }).from(schema.students).where(and(inArray(schema.students.id, studentIds), isNull(schema.students.deletedAt))).orderBy(asc(schema.students.name))
    : [];
  return (
    <>
      <PageHeader title={title} />
      <Filters action={path}>
        <FilterInput name="q" label="Activity" value={sp.q} />
        {children.length > 0 && <FilterSelect name="child" label="Child" value={sp.child} options={children.map((c) => ({ value: c.uuid, label: c.name }))} placeholder="All children" />}
        <FilterSelect name="status" label="Status" value={sp.status} options={AttendanceStatus.options()} placeholder="Any status" />
        <FilterInput name="from" label="From" type="date" value={sp.from} />
        <FilterInput name="to" label="To" type="date" value={sp.to} />
      </Filters>
      {data.rows.length === 0 ? <Empty message="No attendance records yet." /> : (
        <>
          <Table headers={['Date', 'Activity', 'Scout', 'Status', 'Remarks']}>
            {data.rows.map((r) => (
              <tr key={r.id}>
                <td data-label="Date">{formatDate(r.date)}</td>
                <td data-label="Activity">{r.activity}</td>
                <td data-label="Scout">{r.student}</td>
                <td data-label="Status"><Badge of={AttendanceStatus} value={r.status} /></td>
                <td data-label="Remarks">{r.remarks || '—'}</td>
              </tr>
            ))}
          </Table>
          <Pagination page={data.page} pages={data.pages} path={path} query={{ q: sp.q, child: sp.child, status: sp.status, from: sp.from, to: sp.to }} />
        </>
      )}
    </>
  );
}
