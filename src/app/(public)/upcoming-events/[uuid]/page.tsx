import Link from 'next/link';
import { notFound } from 'next/navigation';
import { Badge } from '@/components/Badge';
import { Card } from '@/components/PageHeader';
import { formatDateTime } from '@/lib/dates';
import { EventStatus } from '@/lib/enums';
import { acceptsRegistrations, audienceLabel, sizeLabel, sizeList } from '@/lib/events';
import { formatMoney, isPositive } from '@/lib/money';
import { findPublicEvent, itemsOf, registeredCount } from '@/server/events';
import { getUser } from '@/server/session';

export async function generateMetadata({ params }: { params: Promise<{ uuid: string }> }) {
  const event = await findPublicEvent((await params).uuid);
  return { title: event?.name ?? 'Event' };
}

export default async function PublicEventPage({ params }: { params: Promise<{ uuid: string }> }) {
  const event = await findPublicEvent((await params).uuid);
  if (!event) notFound();
  const [items, count, user] = await Promise.all([itemsOf(event.id, true), registeredCount(event.id), getUser()]);
  const open = acceptsRegistrations(event);
  return (
    <>
      <Link href="/" className="link text-sm">← All events</Link>
      <h1 className="mt-2 text-3xl font-bold">{event.name} <Badge of={EventStatus} value={event.status} /></h1>
      <p className="mt-1 text-gray-600 dark:text-gray-300">{formatDateTime(event.startsAt)}{event.endsAt ? ` – ${formatDateTime(event.endsAt)}` : ''}{event.location ? ` · ${event.location}` : ''}</p>
      <Card className="mt-6 space-y-4 p-6">
        {event.description && <p className="whitespace-pre-line">{event.description}</p>}
        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
          <div><dt className="text-gray-500">Fee</dt><dd className="font-medium">{isPositive(event.fee) ? formatMoney(event.fee) : 'Free'}</dd></div>
          <div><dt className="text-gray-500">Open to</dt><dd className="font-medium">{audienceLabel(event.sections)}</dd></div>
          <div><dt className="text-gray-500">Registered</dt><dd className="font-medium">{count}{event.capacity ? ` of ${event.capacity}` : ''}</dd></div>
          <div><dt className="text-gray-500">Registration closes</dt><dd className="font-medium">{event.registrationClosesAt ? formatDateTime(event.registrationClosesAt) : 'When the event starts'}</dd></div>
        </dl>
        {items.length > 0 && (
          <div>
            <h2 className="mb-2 font-semibold">Pre-order items</h2>
            <ul className="space-y-2 text-sm">
              {items.map((i) => (
                <li key={i.id}>
                  <span className="font-medium">{i.name}</span> — {formatMoney(i.price)}{i.description ? ` · ${i.description}` : ''}
                  {sizeList(i).length > 0 && <ul className="ml-4 list-disc text-xs text-gray-600 dark:text-gray-300">{sizeList(i).map((s) => <li key={s}>{sizeLabel(i, s)}</li>)}</ul>}
                  {i.sizeGuide && <p className="text-xs text-gray-500">{i.sizeGuide}</p>}
                </li>
              ))}
            </ul>
          </div>
        )}
        <div className="pt-2">
          {!open ? <p className="text-sm text-gray-500">Registration is closed.</p>
            : user ? <Link href={`/events/${event.uuid}`} className="btn-primary">Register</Link>
            : <Link href={`/login?next=${encodeURIComponent(`/events/${event.uuid}`)}`} className="btn-primary">Sign in to register</Link>}
        </div>
      </Card>
    </>
  );
}
