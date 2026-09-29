import { forbidden } from 'next/navigation';
import { Badge } from '@/components/Badge';
import { ActionButton, ConfirmButton } from '@/components/ConfirmButton';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { ParentLinkStatus } from '@/lib/enums';
import { formatDateTime } from '@/lib/dates';
import { rejectParentAction, verifyParentAction } from '@/app/actions/users';
import { pendingParents } from '@/server/user-queries';
import { requireUser } from '@/server/session';
import { isStaff } from '@/server/users';

export const metadata = { title: 'Parent registrations' };

export default async function ParentRegistrationsPage({ searchParams }: { searchParams: Promise<{ page?: string }> }) {
  if (!isStaff(await requireUser())) forbidden();
  const page = await pendingParents(currentPage((await searchParams).page));
  return (
    <>
      <PageHeader title="Parent registrations" description="Parents waiting for verification, with the children they asked for." />
      {page.rows.length === 0 ? <Empty message="No parent registrations are waiting." /> : (
        <>
          <Table headers={['Parent', 'National ID', 'Email', 'Requested children', 'Registered', '']}>
            {page.rows.map((p) => (
              <tr key={p.id}>
                <td data-label="Parent" className="font-medium">{p.name}</td>
                <td data-label="National ID">{p.nationalId}</td>
                <td data-label="Email">{p.email}</td>
                <td data-label="Requested children">
                  {p.links.map((l) => <div key={l.nationalId}>{l.name} <span className="text-xs text-gray-400">{l.nationalId}</span> <Badge of={ParentLinkStatus} value={l.status} /></div>)}
                </td>
                <td data-label="Registered">{formatDateTime(p.createdAt)}</td>
                <td className="whitespace-nowrap text-right">
                  <div className="flex flex-wrap justify-end gap-2">
                    <ActionButton action={verifyParentAction} label="Verify" variant="accent" fields={{ uuid: p.uuid }} />
                    <ConfirmButton action={rejectParentAction} fields={{ uuid: p.uuid }} label="Decline" message={`Decline ${p.name}? Their requested links are rejected.`} confirm="Decline" />
                  </div>
                </td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/parent-registrations" query={{}} />
        </>
      )}
    </>
  );
}
