import { PageHeader } from '@/components/PageHeader';
import { allBadges } from '@/server/badges';
import { accessibleStudents } from '@/server/certificates';
import { requireUser } from '@/server/session';
import { RequestForm } from './RequestForm';

export const metadata = { title: 'Request a badge' };

export default async function RequestBadgePage({ searchParams }: { searchParams: Promise<{ student?: string; section?: string }> }) {
  const user = await requireUser();
  const sp = await searchParams;
  const [students, badges] = await Promise.all([accessibleStudents(user, sp.section), allBadges()]);
  return (
    <>
      <PageHeader title="Request a badge" description="A leader approves the request before the certificate is made." />
      <div className="card max-w-xl">
        <RequestForm
          students={students.map((s) => ({ value: s.uuid, label: `${s.name} (${s.section})` }))}
          badges={badges.map((b) => ({ value: b.uuid, label: `${b.name}${b.section ? ` — ${b.section}` : ''}` }))}
          selected={sp.student}
        />
      </div>
    </>
  );
}
