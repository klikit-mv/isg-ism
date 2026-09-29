import { forbidden } from 'next/navigation';
import { and, desc, isNotNull, isNull } from 'drizzle-orm';
import { db, schema } from '@/db';
import { PageHeader } from '@/components/PageHeader';
import { todayLocal } from '@/lib/dates';
import { templatesOfType } from '@/server/certificate-templates';
import { accessibleStudents, canIssue } from '@/server/certificates';
import { activityScope } from '@/server/scope';
import { requireUser } from '@/server/session';
import { IssueForm } from './IssueForm';

export const metadata = { title: 'Issue certificate' };

export default async function IssueCertificatePage() {
  const user = await requireUser();
  if (!canIssue(user)) forbidden();
  const A = schema.activities;
  const [students, templates, activities] = await Promise.all([
    accessibleStudents(user), templatesOfType('general'),
    db.select({ uuid: A.uuid, name: A.name }).from(A).where(and(isNull(A.deletedAt), isNotNull(A.certificateTemplateId), await activityScope(user))).orderBy(desc(A.date)),
  ]);
  return (
    <>
      <PageHeader title="Issue certificate" description="A general certificate for one scout." />
      <div className="card max-w-xl">
        <IssueForm
          students={students.map((s) => ({ value: s.uuid, label: `${s.name} (${s.section})` }))}
          templates={templates.map((t) => ({ value: t.uuid, label: t.name }))}
          activities={activities.map((a) => ({ value: a.uuid, label: a.name }))}
          today={todayLocal()}
        />
      </div>
    </>
  );
}
