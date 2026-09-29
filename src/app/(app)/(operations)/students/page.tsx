import Link from 'next/link';
import { Badge } from '@/components/Badge';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { ActionButton } from '@/components/ConfirmButton';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { StudentAvatar } from '@/components/StudentAvatar';
import { Table } from '@/components/Table';
import { ScoutSection, StudentStatus } from '@/lib/enums';
import { verifyStudentAction } from '@/app/actions/students';
import { canImport, canManageStudents, canVerifyRegistrations } from '@/server/policies';
import { listStudents, pendingCount } from '@/server/student-queries';
import { isStaff } from '@/server/users';
import { requireUser } from '@/server/session';

export const metadata = { title: 'Students' };

export default async function StudentsPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  const sp = await searchParams;
  const page = await listStudents(user, { q: sp.q, section: sp.section, status: sp.status, page: currentPage(sp.page) });
  const pending = isStaff(user) ? await pendingCount() : 0;
  const canVerify = canVerifyRegistrations(user);

  return (
    <>
      <PageHeader title="Students" description={pending ? `${pending} registration${pending === 1 ? '' : 's'} waiting for verification.` : 'The scout registry.'}>
        {canImport(user) && <Link href="/students/import" className="btn-secondary">Import from Excel</Link>}
        {canManageStudents(user) && <Link href="/students/create" className="btn-primary">Enrol scout</Link>}
      </PageHeader>

      <Filters action="/students">
        <FilterInput name="q" label="Search" value={sp.q} placeholder="Name, National ID or index" />
        <FilterSelect name="section" label="Section" value={sp.section} options={ScoutSection.options()} placeholder="Any section" />
        <FilterSelect name="status" label="Status" value={sp.status} options={StudentStatus.options()} placeholder="Any status" />
      </Filters>

      {page.rows.length === 0 ? (
        <Empty message="No scouts match these filters." />
      ) : (
        <>
          <Table headers={['Scout', 'Index', 'National ID', 'Section', 'Status', '']}>
            {page.rows.map((s) => (
              <tr key={s.id}>
                <td data-label="Scout">
                  <Link href={`/students/${s.uuid}`} className="flex items-center gap-3 font-medium hover:underline">
                    <StudentAvatar student={s} />
                    {s.name}
                  </Link>
                </td>
                <td data-label="Index">{s.indexNumber}</td>
                <td data-label="National ID">{s.nationalId}</td>
                <td data-label="Section"><Badge of={ScoutSection} value={s.section} /></td>
                <td data-label="Status"><Badge of={StudentStatus} value={s.status} /></td>
                <td className="whitespace-nowrap text-right">
                  <div className="flex items-center justify-end gap-2">
                    {s.status === 'pending' && canVerify && <ActionButton action={verifyStudentAction} label="Verify" variant="accent" fields={{ uuid: s.uuid }} />}
                    <Link href={`/students/${s.uuid}`} className="link">Open</Link>
                  </div>
                </td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/students" query={{ q: sp.q, section: sp.section, status: sp.status }} />
        </>
      )}
    </>
  );
}
