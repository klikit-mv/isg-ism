import Link from 'next/link';
import { redirect } from 'next/navigation';
import { Empty } from '@/components/PageHeader';
import { config } from '@/lib/config';
import { formatDateTime } from '@/lib/dates';
import { audienceLabel } from '@/lib/events';
import { formatMoney, isPositive } from '@/lib/money';
import { publicEvents } from '@/server/events';
import { getUser } from '@/server/session';

export default async function Home() {
  if (await getUser()) redirect('/dashboard');
  const events = await publicEvents();
  return (
    <>
      <section className="mb-8">
        <h1 className="text-3xl font-bold">{config.name}</h1>
        <p className="mt-2 text-gray-600 dark:text-gray-300">Upcoming events. Anyone can read the details; sign in to register.</p>
      </section>
      {events.length === 0 ? <Empty message="No upcoming events right now. Check back soon." /> : (
        <div className="grid gap-4 sm:grid-cols-2">
          {events.map(({ event, registered }) => (
            <Link key={event.id} href={`/upcoming-events/${event.uuid}`} className="card block space-y-2 p-5 hover:shadow-md">
              <h2 className="text-lg font-semibold">{event.name}</h2>
              <p className="text-sm text-gray-600 dark:text-gray-300">{formatDateTime(event.startsAt)}{event.location ? ` · ${event.location}` : ''}</p>
              <p className="text-xs text-gray-500">{audienceLabel(event.sections)}</p>
              <div className="flex justify-between text-sm">
                <span>{isPositive(event.fee) ? formatMoney(event.fee) : 'Free'}</span>
                <span className="text-gray-500">{event.status === 'open' ? `${registered} registered` : 'Registration closed'}</span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}
