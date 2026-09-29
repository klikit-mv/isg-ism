import Link from 'next/link';
import { Badge } from '@/components/Badge';
import { ActionButton } from '@/components/ConfirmButton';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Stat } from '@/components/Stat';
import { Table } from '@/components/Table';
import { issueActivityAction } from '@/app/actions/certificates';
import { formatDate } from '@/lib/dates';
import { CertificateStatus, CertificateType } from '@/lib/enums';
import { allBadges } from '@/server/badges';
import { accessibleStudents, activityCertificateSummaries, canIssue, certificateStats, listCertificates } from '@/server/certificates';
import { requireUser } from '@/server/session';
import { isActive, isStaff } from '@/server/users';

export const metadata = { title: 'Certificates' };

export default async function CertificatesPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  const sp = await searchParams;
  const staff = isActive(user) && isStaff(user);
  const [page, stats, badges, students, summaries] = await Promise.all([
    listCertificates(user, { q: sp.q, student: sp.student, type: sp.type, status: sp.status, badge: sp.badge, from: sp.from, to: sp.to, sort: sp.sort, page: currentPage(sp.page) }),
    certificateStats(user), allBadges(), accessibleStudents(user), staff ? activityCertificateSummaries(user) : [],
  ]);
  const query = { q: sp.q, student: sp.student, type: sp.type, status: sp.status, badge: sp.badge, from: sp.from, to: sp.to, sort: sp.sort };
  return (
    <>
      <PageHeader title="Certificates" description="Issued certificates. Anyone can check a number on the verify page.">
        {canIssue(user) && (<><Link href="/certificates/bulk-create" className="btn-secondary">Issue to many</Link><Link href="/certificates/create" className="btn-primary">Issue certificate</Link></>)}
      </PageHeader>
      <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Stat label="Certificates" value={stats.total} />
        <Stat label="This year" value={stats.thisYear} tone="gold" />
        <Stat label="Badge requests waiting" value={stats.requestsPending} />
        <Stat label="Generated today" value={stats.generatedToday} />
      </div>
      {summaries.length > 0 && (
        <div className="card mb-6">
          <h2 className="mb-3 font-semibold">Activity certificates</h2>
          <Table headers={['Activity', 'Date', 'Present or late', 'Issued', 'Missing', '']}>
            {summaries.map(({ activity, present, issued, missing }) => (
              <tr key={activity.id}>
                <td data-label="Activity" className="font-medium">{activity.name}</td>
                <td data-label="Date">{formatDate(activity.date)}</td>
                <td data-label="Present or late">{present}</td>
                <td data-label="Issued">{issued}</td>
                <td data-label="Missing">{missing}</td>
                <td className="text-right">{canIssue(user) && missing > 0 && <ActionButton action={issueActivityAction} label="Issue" variant="accent" fields={{ uuid: activity.uuid }} />}</td>
              </tr>
            ))}
          </Table>
        </div>
      )}
      <Filters action="/certificates">
        <FilterInput name="q" label="Search" value={sp.q} placeholder="Number, scout or title" />
        <FilterSelect name="student" label="Scout" value={sp.student} options={students.map((s) => ({ value: s.uuid, label: s.name }))} placeholder="Any scout" />
        <FilterSelect name="type" label="Type" value={sp.type} options={CertificateType.options()} placeholder="Any type" />
        <FilterSelect name="status" label="Status" value={sp.status} options={CertificateStatus.options()} placeholder="Any status" />
        <FilterSelect name="badge" label="Badge" value={sp.badge} options={badges.map((b) => ({ value: b.uuid, label: b.name }))} placeholder="Any badge" />
        <FilterInput name="from" label="From" type="date" value={sp.from} />
        <FilterInput name="to" label="To" type="date" value={sp.to} />
        <FilterSelect name="sort" label="Order" value={sp.sort} options={[{ value: 'newest', label: 'Newest first' }, { value: 'oldest', label: 'Oldest first' }]} placeholder="Newest first" />
      </Filters>
      {page.rows.length === 0 ? <Empty message="No certificates match these filters." /> : (
        <>
          <Table headers={['Number', 'Scout', 'Certificate', 'Type', 'Awarded', 'Status', '']}>
            {page.rows.map((c) => (
              <tr key={c.id}>
                <td data-label="Number" className="whitespace-nowrap font-medium"><Link href={`/certificates/${c.uuid}`} className="hover:underline">{c.certNumber}</Link></td>
                <td data-label="Scout">{c.studentName}</td>
                <td data-label="Certificate">{c.title || c.badgeName}</td>
                <td data-label="Type"><Badge of={CertificateType} value={c.type} /></td>
                <td data-label="Awarded">{formatDate(c.dateAwarded)}</td>
                <td data-label="Status"><Badge of={CertificateStatus} value={c.status} /></td>
                <td className="text-right"><a href={`/certificates/${c.uuid}/download`} target="_blank" rel="noopener" className="link">PDF</a></td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/certificates" query={query} />
        </>
      )}
    </>
  );
}
