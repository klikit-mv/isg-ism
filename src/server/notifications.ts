import { and, desc, eq, isNull } from 'drizzle-orm';
import { count } from 'drizzle-orm';
import { db, schema } from '@/db';
import { config } from '@/lib/config';
import { formatDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import { activeStaff, usersWithPermission, type AuthUser } from './users';

export interface AlertData {
  title: string;
  body: string;
  url: string | null;
}

/** Outbound channels (email, Telegram) register here so this file stays free of them. */
type Channel = (user: AuthUser, alert: AlertData) => Promise<void>;
const channels: Channel[] = [];
export const registerChannel = (channel: Channel) => channels.push(channel);

/** Store the in-app alert and pass it to the other channels. Never throws. */
export async function send(user: AuthUser | null | undefined, title: string, body: string, url: string | null = null): Promise<void> {
  if (!user) return;
  try {
    await db.insert(schema.notifications).values({
      type: 'ScoutAlert', notifiableType: 'user', notifiableId: user.id,
      data: JSON.stringify({ title, body, url } satisfies AlertData), createdAt: new Date(), updatedAt: new Date(),
    });
    for (const channel of channels) {
      try {
        await channel(user, { title, body, url });
      } catch (error) {
        console.warn('Notification channel failed', user.id, (error as Error).message);
      }
    }
  } catch (error) {
    console.warn('Notification failed', user.id, (error as Error).message);
  }
}

export async function sendMany(users: AuthUser[], title: string, body: string, url: string | null = null): Promise<void> {
  for (const user of users) await send(user, title, body, url);
}

export interface NotificationRow {
  id: string;
  readAt: Date | null;
  createdAt: Date | null;
  data: AlertData;
}

const parse = (row: typeof schema.notifications.$inferSelect): NotificationRow => {
  let data: AlertData = { title: 'Notification', body: '', url: null };
  try { data = { ...data, ...JSON.parse(row.data) }; } catch { /* keep the fallback */ }
  return { id: row.id, readAt: row.readAt, createdAt: row.createdAt, data };
};

const mine = (userId: number) => and(eq(schema.notifications.notifiableType, 'user'), eq(schema.notifications.notifiableId, userId));

export async function unread(userId: number, limit = 8): Promise<NotificationRow[]> {
  const rows = await db.select().from(schema.notifications)
    .where(and(mine(userId), isNull(schema.notifications.readAt))).orderBy(desc(schema.notifications.createdAt)).limit(limit);
  return rows.map(parse);
}

export async function unreadCount(userId: number): Promise<number> {
  const [row] = await db.select({ n: count() }).from(schema.notifications).where(and(mine(userId), isNull(schema.notifications.readAt)));
  return Number(row?.n ?? 0);
}

export async function list(userId: number, limit = 100): Promise<NotificationRow[]> {
  const rows = await db.select().from(schema.notifications).where(mine(userId)).orderBy(desc(schema.notifications.createdAt)).limit(limit);
  return rows.map(parse);
}

export async function find(userId: number, id: string): Promise<NotificationRow | null> {
  const [row] = await db.select().from(schema.notifications).where(and(mine(userId), eq(schema.notifications.id, id))).limit(1);
  return row ? parse(row) : null;
}

export async function markRead(userId: number, id: string): Promise<void> {
  await db.update(schema.notifications).set({ readAt: new Date() }).where(and(mine(userId), eq(schema.notifications.id, id), isNull(schema.notifications.readAt)));
}

export async function markAllRead(userId: number): Promise<void> {
  await db.update(schema.notifications).set({ readAt: new Date() }).where(and(mine(userId), isNull(schema.notifications.readAt)));
}

// Who hears about what.
export const notify = {
  async studentRegistered(student: { uuid: string; name: string; section: string }) {
    await sendMany(await activeStaff(), 'New scout registration', `${student.name} (${student.section}) registered and is waiting for verification.`, `/students/${student.uuid}`);
  },
  async parentRegistered(parent: { name: string }) {
    await sendMany(await activeStaff(), 'New parent registration', `${parent.name} registered as a parent and is waiting for verification.`, '/parent-registrations');
  },
  async parentVerified(parent: AuthUser) {
    await send(parent, 'Your parent account is verified', 'A leader verified your registration. You can now sign in and see your children.', '/family');
  },
  async studentVerified(user: AuthUser | null) {
    await send(user, 'Your registration is verified', `A leader verified your registration. Welcome to ${config.name}!`, '/self');
  },
  async paymentSubmitted(payment: { amount: string }, name: string) {
    await sendMany(await usersWithPermission('canVerifyPayments'), 'Payment waiting for verification', `An online payment of ${formatMoney(payment.amount)} for ${name} needs checking.`, '/payment-verification');
  },
  async paymentDecided(submitter: AuthUser | null, payment: { amount: string; status: string; rejectionReason: string | null }) {
    const approved = payment.status === 'Paid';
    await send(
      submitter,
      approved ? 'Payment approved' : 'Payment rejected',
      approved ? `Your payment of ${formatMoney(payment.amount)} was approved.` : `Your payment of ${formatMoney(payment.amount)} was rejected. Reason: ${payment.rejectionReason}`,
      '/payments',
    );
  },
  activityBody: (name: string, date: string) => `You are expected to attend ${name} on ${formatDate(date)}.`,
};
