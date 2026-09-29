'use server';

import { eq } from 'drizzle-orm';
import { redirect } from 'next/navigation';
import { db, schema } from '@/db';
import { handle, echo, simple, type ActionState } from '@/server/action';
import { authenticate, changePin, destroyOtherSessions } from '@/server/auth';
import { recordAudit } from '@/server/audit';
import { flash } from '@/server/flash';
import * as limiter from '@/server/ratelimit';
import { registerParent } from '@/server/parents';
import { registerStudent } from '@/server/students';
import { clientIp, currentSessionToken, endSession, requireUser, startSession } from '@/server/session';
import { safeNext } from '@/lib/safe-next';
import { parseForm, validate } from '@/lib/validate';
import { activeLinkExists, findChildByNationalId } from '@/server/family-lookup';
import { normalizeNationalId } from '@/server/auth';

export async function loginAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const input = parseForm(formData);
  return handle(async () => {
    const user = await authenticate(String(input.national_id ?? ''), String(input.pin ?? ''), await clientIp());
    await startSession(user.id);
    redirect(safeNext(input.next));
  }, echo(input));
}

export async function logoutAction(): Promise<void> {
  const user = await requireUser();
  await recordAudit('auth.logout', { type: 'user', id: user.id }, {}, user.id);
  await endSession();
  redirect('/login');
}

export async function registerScoutAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const input = parseForm(formData);
  const ip = await clientIp();
  return handle(async () => {
    const key = `registration|${ip}`;
    if (await limiter.tooManyAttempts(key, 8)) throw new (await import('@/server/errors')).ScoutError('Too many registrations from this address. Please wait a minute.');
    await limiter.hit(key);
    await registerStudent(input);
    await flash('success', 'Thank you for registering. A leader will verify your details before you can sign in.');
    redirect('/login');
  }, echo(input));
}

export async function registerParentAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const input = parseForm(formData);
  const ip = await clientIp();
  return handle(async () => {
    const key = `registration|${ip}`;
    if (await limiter.tooManyAttempts(key, 8)) throw new (await import('@/server/errors')).ScoutError('Too many registrations from this address. Please wait a minute.');
    await limiter.hit(key);
    await registerParent(input);
    await flash('success', 'Thank you for registering. A leader will verify your account and children before you can sign in.');
    redirect('/login');
  }, echo(input));
}

export interface ChildLookup {
  ok: boolean;
  message?: string;
  child?: { nationalId: string; name: string; section: string };
}

/** Live lookup on the parent registration form (rate limited by address). */
export async function lookupChildAction(nationalIdInput: string, already: string[]): Promise<ChildLookup> {
  const nationalId = normalizeNationalId(nationalIdInput);
  if (nationalId.length < 4) return { ok: false };

  const key = `child-lookup|${await clientIp()}`;
  if (await limiter.tooManyAttempts(key, 40)) return { ok: false, message: 'Too many lookups. Please wait a minute and try again.' };
  await limiter.hit(key, 60);

  const student = await findChildByNationalId(nationalId);
  if (!student) return { ok: false, message: nationalId.length >= 6 ? 'No scout matches that National ID.' : undefined };
  if (already.includes(student.nationalId)) return { ok: false, message: 'This scout is already in your list.' };
  if (await activeLinkExists(student.id)) return { ok: false, message: 'This scout is already added under another parent.' };
  return { ok: true, child: { nationalId: student.nationalId, name: student.name, section: student.section } };
}

export async function changePinAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await requireUser();
  const input = parseForm(formData);
  return handle(async () => {
    await changePin(user, String(input.current_pin ?? ''), String(input.pin ?? ''), String(input.pin_confirmation ?? ''));
    await destroyOtherSessions(user.id, await currentSessionToken());
    await flash('success', 'Your PIN was changed.');
    redirect('/profile');
  });
}

export async function updateProfileAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await requireUser();
  const input = parseForm(formData);
  return handle(async () => {
    const { unique } = await import('@/server/rules');
    const data = await validate(input, {
      name: ['required', 'string', 'max:255'],
      email: ['nullable', 'email', 'max:255', unique(schema.users, schema.users.email, user.id, schema.users.id)],
      email_notifications_enabled: ['nullable', 'boolean'],
      telegram_notifications_enabled: ['nullable', 'boolean'],
    });
    await db.update(schema.users).set({
      name: data.name,
      email: data.email ?? null,
      emailNotificationsEnabled: data.email_notifications_enabled ?? user.emailNotificationsEnabled,
      telegramNotificationsEnabled: user.telegramChatId ? (data.telegram_notifications_enabled ?? user.telegramNotificationsEnabled) : false,
      updatedAt: new Date(),
    }).where(eq(schema.users.id, user.id));
    await recordAudit('profile.updated', { type: 'user', id: user.id }, {}, user.id);
    await flash('success', 'Your details were saved.');
    redirect('/profile');
  }, echo(input));
}

export async function markNotificationReadAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  await simple(async () => {
    const { markRead, markAllRead } = await import('@/server/notifications');
    const id = String(formData.get('id') ?? '');
    if (id === 'all') await markAllRead(user.id);
    else if (id) await markRead(user.id, id);
  });
}
