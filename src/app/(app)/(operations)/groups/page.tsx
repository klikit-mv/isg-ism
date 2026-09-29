import Link from 'next/link';
import { Badge } from '@/components/Badge';
import { FilterInput, Filters } from '@/components/Filters';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { ModalButton } from '@/components/Modal';
import { RecordStatus, ScoutSection } from '@/lib/enums';
import { listGroups } from '@/server/groups';
import { requireUser } from '@/server/session';
import { isActive, isAdmin, isLeader } from '@/server/users';
import { forbidden } from 'next/navigation';
import { CreateGroupModal } from './CreateGroupModal';

export const metadata = { title: 'Groups' };

export default async function GroupsPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  if (!isAdmin(user) && !(isActive(user) && isLeader(user))) forbidden();
  const sp = await searchParams;
  const page = await listGroups(user, sp.q, currentPage(sp.page));
  const admin = isAdmin(user);
  return (
    <>
      <PageHeader title="Groups" description="Patrols, sixes and crews. Leaders see the scouts in the groups they lead.">
        {admin && <ModalButton name="create-group">Create group</ModalButton>}
      </PageHeader>
      <Filters action="/groups"><FilterInput name="q" label="Search" value={sp.q} placeholder="Group name" /></Filters>
      {page.rows.length === 0 ? <Empty message="No groups yet." /> : (
        <>
          <Table headers={['Group', 'Type', 'Section', 'Members', 'Leaders', 'Rover assistants', 'Status', '']}>
            {page.rows.map((g) => (
              <tr key={g.id}>
                <td data-label="Group" className="font-medium"><Link href={`/groups/${g.uuid}`} className="hover:underline">{g.name}</Link></td>
                <td data-label="Type">{g.type || '—'}</td>
                <td data-label="Section">{g.section ? <Badge of={ScoutSection} value={g.section} /> : <span className="text-xs text-gray-500">Mixed</span>}</td>
                <td data-label="Members">{g.members}</td>
                <td data-label="Leaders">{g.leaders}</td>
                <td data-label="Rover assistants">{g.assistants}</td>
                <td data-label="Status"><Badge of={RecordStatus} value={g.status} /></td>
                <td className="text-right"><Link href={`/groups/${g.uuid}`} className="link">Manage</Link></td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/groups" query={{ q: sp.q }} />
        </>
      )}
      {admin && <CreateGroupModal />}
    </>
  );
}
