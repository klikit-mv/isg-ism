import Link from 'next/link';
import { and, count, desc, eq, inArray, like, isNull } from 'drizzle-orm';
import { db, schema } from '@/db';
import { FilterInput, Filters } from './Filters';
import { currentPage, PAGE_SIZE, pageOf, Pagination } from './Pagination';
import { Empty, PageHeader } from './PageHeader';
import { Table } from './Table';
import { formatDate } from '@/lib/dates';
import { activityScope } from '@/server/scope';
import { targetSummary } from '@/server/activities';
import { requireStaff } from '@/server/session';

/** Pick an existing activity to mark (attendance or Rover register). */
export async function ActivityPicker({ title, basePath, searchParams }: { title: string; basePath: string; searchParams: Record<string, string | undefined> }) {
  const user = await requireStaff();
  const A = schema.activities;
  const page = currentPage(searchParams.page);
  const where = and(isNull(A.deletedAt), searchParams.q ? like(A.name, `%${searchParams.q}%`) : undefined, await activityScope(user));
  const [{ n }] = await db.select({ n: count() }).from(A).where(where);
  const rows = await db.select().from(A).where(where).orderBy(desc(A.date), desc(A.id)).limit(PAGE_SIZE).offset((page - 1) * PAGE_SIZE);
  const ids = rows.map((r) => r.id);
  const [marked, sections, groups] = ids.length ? await Promise.all([
    db.select({ id: schema.attendanceRecords.activityId, n: count() }).from(schema.attendanceRecords).where(inArray(schema.attendanceRecords.activityId, ids)).groupBy(schema.attendanceRecords.activityId),
    db.select().from(schema.activitySections).where(inArray(schema.activitySections.activityId, ids)),
    db.select({ activityId: schema.activityGroups.activityId, name: schema.groups.name }).from(schema.activityGroups).innerJoin(schema.groups, eq(schema.groups.id, schema.activityGroups.groupId)).where(inArray(schema.activityGroups.activityId, ids)),
  ]) : [[], [], []];
  const data = pageOf(rows, Number(n), page);

  return (
    <>
      <PageHeader title={title} description="Choose an activity. New activities are created on the Activities page." />
      <Filters action={basePath}><FilterInput name="q" label="Activity" value={searchParams.q} /></Filters>
      {data.rows.length === 0 ? <Empty message="No activities are available to you." /> : (
        <>
          <Table headers={['Date', 'Activity', 'Roster', 'Marked', '']}>
            {data.rows.map((a) => (
              <tr key={a.id}>
                <td data-label="Date">{formatDate(a.date)}</td>
                <td data-label="Activity" className="font-medium">{a.name}</td>
                <td data-label="Roster" className="text-xs">{targetSummary(a.allStudents, sections.filter((s) => s.activityId === a.id).map((s) => s.section), groups.filter((g) => g.activityId === a.id).map((g) => g.name))}</td>
                <td data-label="Marked">{Number(marked.find((m) => m.id === a.id)?.n ?? 0)}</td>
                <td className="text-right"><Link href={`${basePath}/${a.uuid}/mark`} className="btn-primary btn-sm">Open register</Link></td>
              </tr>
            ))}
          </Table>
          <Pagination page={data.page} pages={data.pages} path={basePath} query={{ q: searchParams.q }} />
        </>
      )}
    </>
  );
}
