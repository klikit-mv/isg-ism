import Link from 'next/link';
import { Badge } from '@/components/Badge';
import { Empty, PageHeader } from '@/components/PageHeader';
import { formatDateTime } from '@/lib/dates';
import { EventStatus } from '@/lib/enums';
import { audienceLabel } from '@/lib/events';
import { formatMoney, isPositive } from '@/lib/money';
import { listEvents } from '@/server/events';
import { requireUser } from '@/server/session';
import { isActive, isStaff } from '@/server/users';

export const metadata = { title: 'Events' };

export default async function EventsPage({ searchParams }: { searchParams: Promise<{ show?: string }> }) {
  const user = await requireUser();
  const past = (await searchParams).show === 'past';
  const events = await listEvents(user, past);
  return (
    <>
      <PageHeader title="Events" description="Camps and events: register, pre-order items and pay.">
        <Link href={past ? '/events' : '/events?show=past'} className="btn-secondary">{past ? 'Upcoming events' : 'Past events'}</Link>
        {isActive(user) && isStaff(user) && <Link href="/events/create" className="btn-primary">New event</Link>}
      </PageHeader>
      {events.length === 0 ? <Empty message={past ? 'No past events.' : 'No upcoming events.'} /> : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {events.map(({ event, registered }) => (
            <Link key={event.id} href={`/events/${event.uuid}`} className="card block space-y-2 p-4 hover:shadow-md">
              <div className="flex items-start justify-between gap-2">
                <h3 className="font-semibold">{event.name}</h3>
                <Badge of={EventStatus} value={event.status} />
              </div>
              <p className="text-sm text-gray-600 dark:text-gray-300">{formatDateTime(event.startsAt)}{event.location ? ` · ${event.location}` : ''}</p>
              <p className="text-xs text-gray-500">{audienceLabel(event.sections)}</p>
              <div className="flex justify-between text-sm">
                <span>{isPositive(event.fee) ? formatMoney(event.fee) : 'Free'}</span>
                <span className="text-gray-500">{registered}{event.capacity ? ` / ${event.capacity}` : ''} registered</span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}
