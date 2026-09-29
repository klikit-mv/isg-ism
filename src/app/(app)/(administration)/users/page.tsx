import Link from 'next/link';
import { Badge } from '@/components/Badge';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { Role, UserStatus } from '@/lib/enums';
import { listUsers } from '@/server/user-queries';

export const metadata = { title: 'Users' };

export default async function UsersPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const sp = await searchParams;
  const page = await listUsers({ q: sp.q, role: sp.role, status: sp.status, page: currentPage(sp.page) });
  return (
    <>
      <PageHeader title="Users" description="Accounts, roles and permissions.">
        <Link href="/users/create" className="btn-primary">Create user</Link>
      </PageHeader>
      <Filters action="/users">
        <FilterInput name="q" label="Search" value={sp.q} placeholder="Name, National ID or email" />
        <FilterSelect name="role" label="Role" value={sp.role} options={Role.options()} placeholder="Any role" />
        <FilterSelect name="status" label="Status" value={sp.status} options={UserStatus.options()} placeholder="Any status" />
      </Filters>
      {page.rows.length === 0 ? <Empty message="No users match these filters." /> : (
        <>
          <Table headers={['Name', 'National ID', 'Email', 'Roles', 'Status', '']}>
            {page.rows.map((u) => (
              <tr key={u.id}>
                <td data-label="Name" className="font-medium">{u.name}</td>
                <td data-label="National ID">{u.nationalId}</td>
                <td data-label="Email">{u.email || '—'}</td>
                <td data-label="Roles"><div className="flex flex-wrap justify-end gap-1 md:justify-start">{u.roles.map((r) => <Badge key={r} of={Role} value={r} />)}</div></td>
                <td data-label="Status"><Badge of={UserStatus} value={u.status} /></td>
                <td className="text-right"><Link href={`/users/${u.uuid}`} className="link">Edit</Link></td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/users" query={{ q: sp.q, role: sp.role, status: sp.status }} />
        </>
      )}
    </>
  );
}
