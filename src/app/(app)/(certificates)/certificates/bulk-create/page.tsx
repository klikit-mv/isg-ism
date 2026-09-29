import Link from 'next/link';
import { forbidden } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { todayLocal } from '@/lib/dates';
import { ScoutSection } from '@/lib/enums';
import { templatesOfType } from '@/server/certificate-templates';
import { accessibleStudents, canIssue } from '@/server/certificates';
import { requireUser } from '@/server/session';
import { BulkForm } from './BulkForm';

export const metadata = { title: 'Issue certificates' };

export default async function BulkIssuePage({ searchParams }: { searchParams: Promise<{ section?: string }> }) {
  const user = await requireUser();
  if (!canIssue(user)) forbidden();
  const { section } = await searchParams;
  const [students, templates] = await Promise.all([accessibleStudents(user, ScoutSection.is(section) ? section : null), templatesOfType('general')]);
  return (
    <>
      <PageHeader title="Issue to many scouts" description="The same certificate for each scout you tick.">
        <div className="flex flex-wrap gap-2 text-sm">
          <Link href="/certificates/bulk-create" className="link">All sections</Link>
          {ScoutSection.options().map((o) => <Link key={o.value} href={`/certificates/bulk-create?section=${encodeURIComponent(o.value)}`} className="link">{o.label}</Link>)}
        </div>
      </PageHeader>
      <div className="card max-w-2xl">
        <BulkForm students={students.map((s) => ({ value: String(s.id), label: `${s.name} (${s.section})` }))} templates={templates.map((t) => ({ value: t.uuid, label: t.name }))} today={todayLocal()} />
      </div>
    </>
  );
}
