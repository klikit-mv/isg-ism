import { forbidden, notFound } from 'next/navigation';
import { currentPage } from '@/components/Pagination';
import { RECORD_TABS, StudentRecord, type RecordTab } from '@/components/StudentRecord';
import { findStudentByUuid } from '@/server/student-queries';
import { requireUser } from '@/server/session';
import { approvedChildIds } from '@/server/users';

export default async function FamilyStudentPage({ params, searchParams }: { params: Promise<{ uuid: string; tab?: string[] }>; searchParams: Promise<{ page?: string }> }) {
  const [{ uuid, tab = [] }, sp, user] = await Promise.all([params, searchParams, requireUser()]);
  if (tab.length > 1 || (tab[0] && !(tab[0] in RECORD_TABS))) notFound();
  const student = await findStudentByUuid(uuid);
  if (!student) notFound();
  if (!(await approvedChildIds(user.id)).includes(student.id)) forbidden();
  return <StudentRecord viewer={user} student={student} tab={(tab[0] ?? 'profile') as RecordTab} context="family" page={currentPage(sp.page)} />;
}
