import { forbidden } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { HEADERS, REQUIRED } from '@/server/student-import';
import { canManageStudents } from '@/server/policies';
import { requireUser } from '@/server/session';
import { ImportForm } from './ImportForm';

export const metadata = { title: 'Import scouts' };

export default async function ImportStudentsPage() {
  if (!canManageStudents(await requireUser())) forbidden();
  return (
    <>
      <PageHeader title="Import scouts" description="Enrol many scouts from a spreadsheet.">
        <a href="/students/import/template" className="btn-accent">Download template</a>
      </PageHeader>
      <div className="card mb-6 max-w-3xl space-y-2 text-sm">
        <p>Fill in the template (gender, section and status have dropdown lists), save it as .xlsx or .csv and use <strong>Check file</strong> first: nothing is saved until you press <strong>Import scouts</strong>.</p>
        <p>Required columns: <strong>{REQUIRED.join(', ')}</strong>. Other columns: {HEADERS.filter((h) => !(REQUIRED as readonly string[]).includes(h)).join(', ')}. Dates can be dd.mm.yyyy, yyyy-mm-dd or dd/mm/yyyy. Scouts whose National ID already exists are skipped.</p>
      </div>
      <div className="card max-w-3xl"><ImportForm /></div>
    </>
  );
}
