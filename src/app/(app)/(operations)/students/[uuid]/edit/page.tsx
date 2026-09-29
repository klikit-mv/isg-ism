import { forbidden, notFound } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { canManageStudents } from '@/server/policies';
import { findStudentByUuid } from '@/server/student-queries';
import { requireUser } from '@/server/session';
import { StudentForm } from '../../StudentForm';

export const metadata = { title: 'Edit scout' };

export default async function EditStudentPage({ params }: { params: Promise<{ uuid: string }> }) {
  if (!canManageStudents(await requireUser())) forbidden();
  const student = await findStudentByUuid((await params).uuid);
  if (!student) notFound();
  return (
    <>
      <PageHeader title={`Edit ${student.name}`} description="Changes to name, National ID, email and status are copied to their account." />
      <StudentForm student={student} />
    </>
  );
}
