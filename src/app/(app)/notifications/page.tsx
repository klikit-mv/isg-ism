import Link from 'next/link';
import { Empty, PageHeader } from '@/components/PageHeader';
import { ActionButton } from '@/components/ConfirmButton';
import { markNotificationReadAction } from '@/app/actions/auth';
import { formatDateTime } from '@/lib/dates';
import { list } from '@/server/notifications';
import { requireUser } from '@/server/session';

export const metadata = { title: 'Notifications' };

export default async function NotificationsPage() {
  const user = await requireUser();
  const items = await list(user.id);
  return (
    <>
      <PageHeader title="Notifications" description="Alerts about registrations, payments and activities.">
        {items.some((n) => !n.readAt) && <ActionButton action={markNotificationReadAction} label="Mark all as read" fields={{ id: 'all' }} />}
      </PageHeader>
      {items.length === 0 ? (
        <Empty message="You have no notifications yet." />
      ) : (
        <ul className="space-y-3">
          {items.map((n) => (
            <li key={n.id} className={`card !p-4 ${n.readAt ? 'opacity-70' : ''}`}>
              <div className="flex items-start justify-between gap-3">
                <div>
                  <Link href={`/notifications/${n.id}`} className="font-medium hover:underline">{n.data.title}</Link>
                  <p className="text-sm text-gray-600 dark:text-gray-300">{n.data.body}</p>
                </div>
                <span className="whitespace-nowrap text-xs text-gray-500">{formatDateTime(n.createdAt)}</span>
              </div>
            </li>
          ))}
        </ul>
      )}
    </>
  );
}
