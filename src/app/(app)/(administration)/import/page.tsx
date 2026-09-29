import { PageHeader } from '@/components/PageHeader';
import { SHEETS } from '@/server/legacy-import';
import { requireAdmin } from '@/server/session';
import { LegacyForm } from './LegacyForm';

export const metadata = { title: 'Import workbook' };

export default async function ImportPage() {
  await requireAdmin();
  return (
    <>
      <PageHeader title="Import workbook" description="Bring in the old Google Sheets workbooks.">
        <a href="/import/template" className="btn-accent">Download template</a>
      </PageHeader>
      <div className="card mb-6 max-w-3xl space-y-2 text-sm">
        <p>Sheets are imported in this order: {Object.keys(SHEETS).join(', ')}. Headers are matched ignoring case and punctuation (Student ID = student_id). References accept old ids or National IDs, and dates accept dd.MM.yyyy, ISO, d/m/Y and Excel dates.</p>
        <p><strong>Inspect</strong> counts rows only. <strong>Dry run</strong> does everything and then undoes it. Run it again with <strong>Import for real</strong> when the problem list is empty or acceptable. Running the same file twice updates rows instead of duplicating them.</p>
        <p>Users without a PIN in the sheet are imported inactive; reset their PIN from Users to activate them.</p>
      </div>
      <div className="card max-w-3xl"><LegacyForm sheets={Object.keys(SHEETS)} /></div>
    </>
  );
}
