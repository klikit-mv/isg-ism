import { PageHeader } from '@/components/PageHeader';
import { canManageStudents } from '@/server/policies';
import { requireUser } from '@/server/session';
import { forbidden } from 'next/navigation';
import { StudentForm } from '../StudentForm';

export const metadata = { title: 'Enrol scout' };

export default async function CreateStudentPage() {
  if (!canManageStudents(await requireUser())) forbidden();
  return (
    <>
      <PageHeader title="Enrol a scout" description="Creates the scout and their sign-in account." />
      <StudentForm />
    </>
  );
}
