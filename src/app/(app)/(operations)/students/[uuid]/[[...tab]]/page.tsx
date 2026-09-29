import { forbidden, notFound } from 'next/navigation';
import { StudentRecord, RECORD_TABS, type RecordTab } from '@/components/StudentRecord';
import { currentPage } from '@/components/Pagination';
import { canViewStudent } from '@/server/policies';
import { findStudentByUuid } from '@/server/student-queries';
import { requireUser } from '@/server/session';

export default async function StudentPage({ params, searchParams }: { params: Promise<{ uuid: string; tab?: string[] }>; searchParams: Promise<{ page?: string }> }) {
  const [{ uuid, tab = [] }, sp, user] = await Promise.all([params, searchParams, requireUser()]);
  if (tab.length > 1 || (tab[0] && !(tab[0] in RECORD_TABS))) notFound();
  const student = await findStudentByUuid(uuid);
  if (!student) notFound();
  if (!(await canViewStudent(user, student))) forbidden();
  return <StudentRecord viewer={user} student={student} tab={(tab[0] ?? 'profile') as RecordTab} context="students" page={currentPage(sp.page)} />;
}
