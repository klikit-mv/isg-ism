import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { formatDateTime } from '@/lib/dates';
import { find, markRead } from '@/server/notifications';
import { requireUser } from '@/server/session';

/** Opening a notification marks it read, then shows it (or goes to its page). */
export default async function NotificationPage({ params }: { params: Promise<{ id: string }> }) {
  const user = await requireUser();
  const { id } = await params;
  const item = await find(user.id, id);
  if (!item) notFound();
  await markRead(user.id, id);
  if (item.data.url && /^\/(?!\/)/.test(item.data.url)) redirect(item.data.url);
  return (
    <>
      <PageHeader title={item.data.title} description={formatDateTime(item.createdAt)} />
      <div className="card"><p className="whitespace-pre-line text-sm">{item.data.body}</p></div>
      <Link href="/notifications" className="link mt-4 inline-block text-sm">Back to notifications</Link>
    </>
  );
}
