import { forbidden } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { toLocalInput } from '@/lib/dates';
import { requireUser } from '@/server/session';
import { isActive, isStaff } from '@/server/users';
import { EventForm } from '../EventForm';

export const metadata = { title: 'New event' };

export default async function CreateEventPage() {
  const user = await requireUser();
  if (!isActive(user) || !isStaff(user)) forbidden();
  const start = new Date(Date.now() + 7 * 86400_000);
  const startsAt = toLocalInput(start).replace(/T.*/, 'T09:00');
  return (
    <>
      <PageHeader title="New event" description="It starts as a draft. Add pre-order items, then open registration." />
      <div className="card p-5"><EventForm event={{ starts_at: startsAt, fee: '0.00' }} /></div>
    </>
  );
}
