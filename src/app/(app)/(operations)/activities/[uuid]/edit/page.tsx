import Link from 'next/link';
import { and, asc, eq, isNull } from 'drizzle-orm';
import { forbidden, notFound } from 'next/navigation';
import { db, schema } from '@/db';
import { ConfirmButton } from '@/components/ConfirmButton';
import { PageHeader } from '@/components/PageHeader';
import { deleteActivityAction } from '@/app/actions/activities';
import { activityTargets, findActivityByUuid } from '@/server/activities';
import { canAccessActivity } from '@/server/scope';
import { requireUser } from '@/server/session';
import { isActive, isAdmin, isLeader } from '@/server/users';
import { ActivityForm } from '../../ActivityForm';

export const metadata = { title: 'Edit activity' };

export default async function EditActivityPage({ params }: { params: Promise<{ uuid: string }> }) {
  const user = await requireUser();
  const activity = await findActivityByUuid((await params).uuid);
  if (!activity) notFound();
  if (!isAdmin(user) && !(isActive(user) && isLeader(user) && (await canAccessActivity(user, activity.id)))) forbidden();
  const [targets, groups, templates] = await Promise.all([
    activityTargets(activity.id),
    db.select({ id: schema.groups.id, name: schema.groups.name, type: schema.groups.type }).from(schema.groups).where(isNull(schema.groups.deletedAt)).orderBy(asc(schema.groups.name)),
    db.select({ id: schema.certificateTemplates.id, name: schema.certificateTemplates.name }).from(schema.certificateTemplates).where(and(eq(schema.certificateTemplates.type, 'general'), eq(schema.certificateTemplates.active, true))).orderBy(asc(schema.certificateTemplates.name)),
  ]);
  return (
    <>
      <PageHeader title={`Edit ${activity.name}`}>
        <Link href="/activities" className="btn-secondary">Back</Link>
        {isAdmin(user) && <ConfirmButton action={deleteActivityAction} fields={{ uuid: activity.uuid }} label="Delete" size="md" message="Delete this activity? Attendance and fees are kept." confirm="Delete" />}
      </PageHeader>
      <ActivityForm
        activity={{ uuid: activity.uuid, name: activity.name, date: activity.date, details: activity.details, allStudents: activity.allStudents, chargeFee: activity.chargeFee, feeAmount: activity.feeAmount, certificateTemplateId: activity.certificateTemplateId, sections: targets.sections, groups: targets.groups.map((g) => g.id) }}
        groupOptions={groups.map((g) => ({ id: g.id, label: g.name, hint: g.type ?? '' }))}
        templateOptions={templates.map((t) => ({ value: String(t.id), label: t.name }))}
      />
    </>
  );
}
