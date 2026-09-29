import Link from 'next/link';
import { eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import { ActionButton, ConfirmButton } from '@/components/ConfirmButton';
import { FilterInput, Filters } from '@/components/Filters';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { deleteLeadershipAction, generateLeadershipAction } from '@/app/actions/certificates';
import { formatDate } from '@/lib/dates';
import { accessibleStudents } from '@/server/certificates';
import { listLeadership } from '@/server/leadership';
import { requireUser } from '@/server/session';
import { isActive, isAdmin, isLeader } from '@/server/users';
import { LeadershipModal } from './LeadershipModal';

export const metadata = { title: 'Leadership' };

export default async function LeadershipPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  const sp = await searchParams;
  const staff = isAdmin(user) || (isActive(user) && isLeader(user));
  const [page, students] = await Promise.all([listLeadership(user, { q: sp.q, page: currentPage(sp.page) }), staff ? accessibleStudents(user) : []]);
  const options = students.map((s) => ({ value: s.uuid, label: `${s.name} (${s.section})` }));
  const uuids = new Map((await db.select({ id: schema.students.id, uuid: schema.students.uuid }).from(schema.students)).map((s) => [s.id, s.uuid]));
  return (
    <>
      <PageHeader title="Leadership" description="Patrol, six and crew leadership records, with their certificates.">
        {staff && <LeadershipModal students={options} />}
      </PageHeader>
      <Filters action="/leadership"><FilterInput name="q" label="Search" value={sp.q} placeholder="Scout, patrol or group" /></Filters>
      {page.rows.length === 0 ? <Empty message="No leadership records yet." /> : (
        <>
          <Table headers={['Scout', 'Patrol or six', 'Group', 'From', 'To', 'Certificate', '']}>
            {page.rows.map(({ record: r, student, certNumber, certUuid }) => (
              <tr key={r.id}>
                <td data-label="Scout" className="font-medium">{student}</td>
                <td data-label="Patrol or six">{r.patrolOrSix}</td>
                <td data-label="Group">{r.troopOrGroup}</td>
                <td data-label="From">{formatDate(r.startDate)}</td>
                <td data-label="To">{r.endDate ? formatDate(r.endDate) : '—'}</td>
                <td data-label="Certificate">{certUuid ? <Link href={`/certificates/${certUuid}`} className="link">{certNumber}</Link> : '—'}</td>
                <td className="text-right">
                  {staff && (
                    <div className="flex justify-end gap-2">
                      <ActionButton action={generateLeadershipAction} label={certUuid ? 'Refresh certificate' : 'Generate certificate'} variant="accent" fields={{ uuid: r.uuid }} />
                      <LeadershipModal students={options} record={{ uuid: r.uuid, studentUuid: uuids.get(r.studentId) ?? '', patrolOrSix: r.patrolOrSix, troopOrGroup: r.troopOrGroup, startDate: r.startDate, endDate: r.endDate }} />
                      <ConfirmButton action={deleteLeadershipAction} label="Delete" title="Delete record" message="Delete this leadership record? Its certificate is kept." confirm="Delete" fields={{ uuid: r.uuid }} />
                    </div>
                  )}
                </td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/leadership" query={{ q: sp.q }} />
        </>
      )}
    </>
  );
}
