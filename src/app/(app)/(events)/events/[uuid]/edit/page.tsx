import { forbidden, notFound } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { toLocalInput } from '@/lib/dates';
import { canManageEvent, findEventByUuid } from '@/server/events';
import { requireUser } from '@/server/session';
import { EventForm } from '../../EventForm';

export const metadata = { title: 'Edit event' };

export default async function EditEventPage({ params }: { params: Promise<{ uuid: string }> }) {
  const user = await requireUser();
  const event = await findEventByUuid((await params).uuid);
  if (!event) notFound();
  if (!canManageEvent(user, event)) forbidden();
  return (
    <>
      <PageHeader title={`Edit ${event.name}`} />
      <div className="card p-5">
        <EventForm event={{
          uuid: event.uuid, name: event.name, description: event.description, location: event.location,
          starts_at: toLocalInput(event.startsAt), ends_at: toLocalInput(event.endsAt), registration_closes_at: toLocalInput(event.registrationClosesAt),
          fee: event.fee, capacity: event.capacity, sections: event.sections ?? [],
        }} />
      </div>
    </>
  );
}
