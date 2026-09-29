import Link from 'next/link';
import { asc, and, eq, isNull } from 'drizzle-orm';
import { forbidden } from 'next/navigation';
import { db, schema } from '@/db';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { Modal, ModalButton } from '@/components/Modal';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { formatDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import { listActivities } from '@/server/activities';
import { canAccessActivity } from '@/server/scope';
import { requireUser } from '@/server/session';
import { isActive, isAdmin, isLeader } from '@/server/users';
import { ActivityForm } from './ActivityForm';

export const metadata = { title: 'Activities' };

export default async function ActivitiesPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  if (!isAdmin(user) && !(isActive(user) && isLeader(user))) forbidden();
  const sp = await searchParams;
  const page = await listActivities(user, { q: sp.q, from: sp.from, to: sp.to, charged: sp.charged, certificate: sp.certificate, page: currentPage(sp.page) });
  const [groups, templates] = await Promise.all([
    db.select({ id: schema.groups.id, name: schema.groups.name, type: schema.groups.type }).from(schema.groups).where(isNull(schema.groups.deletedAt)).orderBy(asc(schema.groups.name)),
    db.select({ id: schema.certificateTemplates.id, name: schema.certificateTemplates.name }).from(schema.certificateTemplates).where(and(eq(schema.certificateTemplates.type, 'general'), eq(schema.certificateTemplates.active, true))).orderBy(asc(schema.certificateTemplates.name)),
  ]);
  const editable = new Map<number, boolean>();
  for (const a of page.rows) editable.set(a.id, await canAccessActivity(user, a.id));
  const yesNo = [{ value: 'yes', label: 'Yes' }, { value: 'no', label: 'No' }];

  return (
    <>
      <PageHeader title="Activities" description="Dated sessions with a target roster.">
        <ModalButton name="create-activity">Create activity</ModalButton>
      </PageHeader>
      <Filters action="/activities">
        <FilterInput name="q" label="Name" value={sp.q} />
        <FilterInput name="from" label="From" type="date" value={sp.from} />
        <FilterInput name="to" label="To" type="date" value={sp.to} />
        <FilterSelect name="charged" label="Charged" value={sp.charged} options={yesNo} />
        <FilterSelect name="certificate" label="Has certificate" value={sp.certificate} options={yesNo} />
      </Filters>
      {page.rows.length === 0 ? <Empty message="No activities match these filters." /> : (
        <>
          <Table headers={['Date', 'Activity', 'Roster', 'Fee', 'Certificate', 'Marked', '']}>
            {page.rows.map((a) => (
              <tr key={a.id}>
                <td data-label="Date" className="whitespace-nowrap">{formatDate(a.date)}</td>
                <td data-label="Activity" className="font-medium">{a.name}</td>
                <td data-label="Roster" className="text-xs">{a.target}</td>
                <td data-label="Fee">{a.chargeFee ? formatMoney(a.feeAmount) : '—'}</td>
                <td data-label="Certificate">{a.template ?? '—'}</td>
                <td data-label="Marked">{a.marked}</td>
                <td className="whitespace-nowrap text-right">
                  <Link href={`/attendance/${a.uuid}/mark`} className="link">Mark</Link>
                  {editable.get(a.id) && <Link href={`/activities/${a.uuid}/edit`} className="link ml-2">Edit</Link>}
                </td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/activities" query={{ q: sp.q, from: sp.from, to: sp.to, charged: sp.charged, certificate: sp.certificate }} />
        </>
      )}
      <Modal name="create-activity" title="Create activity" maxWidth="2xl">
        <ActivityForm modal="create-activity" groupOptions={groups.map((g) => ({ id: g.id, label: g.name, hint: g.type ?? '' }))} templateOptions={templates.map((t) => ({ value: String(t.id), label: t.name }))} />
      </Modal>
    </>
  );
}
