import Link from 'next/link';
import { forbidden, notFound } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { formatDate } from '@/lib/dates';
import { activityTargets, findActivityByUuid, targetSummary } from '@/server/activities';
import { registerRows } from '@/server/attendance-queries';
import { amountFor } from '@/server/class-fees';
import { canManageAttendance } from '@/server/scope';
import { requireUser } from '@/server/session';
import { Register } from './Register';

export default async function MarkPage({ params }: { params: Promise<{ uuid: string }> }) {
  const user = await requireUser();
  const activity = await findActivityByUuid((await params).uuid);
  if (!activity) notFound();
  if (!(await canManageAttendance(user, activity.id))) forbidden();
  const [rows, targets, fee] = await Promise.all([registerRows(activity, user), activityTargets(activity.id), activity.chargeFee ? amountFor(activity) : Promise.resolve('')]);
  return (
    <>
      <PageHeader title={activity.name} description={`${formatDate(activity.date)} · ${targetSummary(activity.allStudents, targets.sections, targets.groups.map((g) => g.name))}`}>
        <Link href="/attendance" className="btn-secondary">All activities</Link>
      </PageHeader>
      <Register uuid={activity.uuid} chargeFee={activity.chargeFee} feeDue={fee} initial={rows} />
    </>
  );
}
