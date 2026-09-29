import Link from 'next/link';
import { forbidden, notFound } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { formatDate } from '@/lib/dates';
import { findActivityByUuid } from '@/server/activities';
import { participants, roverMarks } from '@/server/rover-attendance';
import { canManageAttendance } from '@/server/scope';
import { requireUser } from '@/server/session';
import { RoverRegister } from './RoverRegister';

export default async function RoverMarkPage({ params }: { params: Promise<{ uuid: string }> }) {
  const user = await requireUser();
  const activity = await findActivityByUuid((await params).uuid);
  if (!activity) notFound();
  if (!(await canManageAttendance(user, activity.id))) forbidden();
  const [p, marks] = await Promise.all([participants(activity), roverMarks(activity.id)]);
  const pick = (list: typeof p.required) => list.map((s) => ({ id: s.id, name: s.name }));
  return (
    <>
      <PageHeader title={`Rovers · ${activity.name}`} description={formatDate(activity.date)}>
        <Link href="/rover-attendance" className="btn-secondary">All activities</Link>
      </PageHeader>
      <RoverRegister uuid={activity.uuid} required={pick(p.required)} optional={pick([...p.optional, ...p.additional])} available={pick(p.available)} initial={marks} />
    </>
  );
}
