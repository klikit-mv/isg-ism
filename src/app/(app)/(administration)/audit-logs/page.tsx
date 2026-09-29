import { and, count, desc, eq, gte, isNotNull, like, lt } from 'drizzle-orm';
import { db, schema } from '@/db';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { currentPage, PAGE_SIZE, pageOf, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { formatDateTime } from '@/lib/dates';

export const metadata = { title: 'Audit logs' };

/** The start of a calendar day in the organisation timezone, as an instant. */
import { fromLocalInput } from '@/lib/dates';
const dayStart = (d: string) => fromLocalInput(`${d}T00:00`);
const dayAfter = (d: string) => {
  const next = new Date(`${d}T00:00:00Z`);
  next.setUTCDate(next.getUTCDate() + 1);
  return fromLocalInput(`${next.toISOString().slice(0, 10)}T00:00`);
};

export default async function AuditLogsPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const sp = await searchParams;
  const L = schema.auditLogs;
  const page = currentPage(sp.page);
  const valid = (d?: string) => (d && /^\d{4}-\d{2}-\d{2}$/.test(d) ? d : undefined);
  const from = valid(sp.from);
  const to = valid(sp.to);
  const where = and(
    sp.action ? like(L.action, `${sp.action}%`) : undefined,
    sp.entity ? eq(L.entityType, sp.entity) : undefined,
    from ? gte(L.createdAt, dayStart(from)!) : undefined,
    to ? lt(L.createdAt, dayAfter(to)!) : undefined,
  );
  const [{ n }] = await db.select({ n: count() }).from(L).where(where);
  const rows = await db.select({ log: L, actor: schema.users.name }).from(L).leftJoin(schema.users, eq(schema.users.id, L.actorUserId))
    .where(where).orderBy(desc(L.id)).limit(PAGE_SIZE).offset((page - 1) * PAGE_SIZE);
  const entities = await db.selectDistinct({ t: L.entityType }).from(L).where(isNotNull(L.entityType)).orderBy(L.entityType);
  const data = pageOf(rows, Number(n), page);

  return (
    <>
      <PageHeader title="Audit logs" description="Append-only record of financial, membership, attendance, import and sign-in events." />
      <Filters action="/audit-logs">
        <FilterInput name="action" label="Action starts with" value={sp.action} placeholder="payment." />
        <FilterSelect name="entity" label="Entity" value={sp.entity} options={entities.map((e) => ({ value: e.t!, label: e.t! }))} placeholder="Any" />
        <FilterInput name="from" label="From" type="date" value={sp.from} />
        <FilterInput name="to" label="To" type="date" value={sp.to} />
      </Filters>
      {data.rows.length === 0 ? <Empty message="No audit entries match these filters." /> : (
        <>
          <Table headers={['When', 'Action', 'Entity', 'By', 'Details']}>
            {data.rows.map(({ log, actor }) => (
              <tr key={log.id}>
                <td data-label="When" className="whitespace-nowrap">{formatDateTime(log.createdAt)}</td>
                <td data-label="Action" className="font-mono text-xs">{log.action}</td>
                <td data-label="Entity" className="text-xs">{log.entityType} <span className="block font-mono text-gray-400">{(log.entityId ?? '').slice(0, 13)}</span></td>
                <td data-label="By">{actor ?? 'System'}</td>
                <td data-label="Details" className="max-w-md break-words font-mono text-xs text-gray-500">{log.details && Object.keys(log.details).length ? JSON.stringify(log.details) : ''}</td>
              </tr>
            ))}
          </Table>
          <Pagination page={data.page} pages={data.pages} path="/audit-logs" query={{ action: sp.action, entity: sp.entity, from: sp.from, to: sp.to }} />
        </>
      )}
    </>
  );
}
