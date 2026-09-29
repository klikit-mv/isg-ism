import Link from 'next/link';
import { Badge } from '@/components/Badge';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { formatDateTime } from '@/lib/dates';
import { BadgeRequestStatus } from '@/lib/enums';
import { listRequests } from '@/server/certificates';
import { requireUser } from '@/server/session';

export const metadata = { title: 'Badge requests' };

export default async function BadgeRequestsPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  const sp = await searchParams;
  const page = await listRequests(user, { status: sp.status, q: sp.q, page: currentPage(sp.page) });
  return (
    <>
      <PageHeader title="Badge requests" description="Ask for a badge; a leader approves it and the certificate is generated.">
        <Link href="/badge-requests/create" className="btn-primary">Request a badge</Link>
      </PageHeader>
      <Filters action="/badge-requests">
        <FilterInput name="q" label="Search" value={sp.q} placeholder="Scout, badge or request id" />
        <FilterSelect name="status" label="Status" value={sp.status} options={BadgeRequestStatus.options()} placeholder="Any status" />
      </Filters>
      {page.rows.length === 0 ? <Empty message="No badge requests yet." /> : (
        <>
          <Table headers={['Request', 'Scout', 'Badge', 'Requested', 'Status', 'Certificate']}>
            {page.rows.map((r) => (
              <tr key={r.id}>
                <td data-label="Request" className="font-medium"><Link href={`/badge-requests/${r.uuid}`} className="hover:underline">{r.requestId}</Link></td>
                <td data-label="Scout">{r.studentName}</td>
                <td data-label="Badge">{r.badgeName}</td>
                <td data-label="Requested">{formatDateTime(r.createdAt)}</td>
                <td data-label="Status"><Badge of={BadgeRequestStatus} value={r.status} /></td>
                <td data-label="Certificate">{r.certificateNumber ?? '—'}</td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/badge-requests" query={{ q: sp.q, status: sp.status }} />
        </>
      )}
    </>
  );
}
