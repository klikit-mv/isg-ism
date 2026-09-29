'use server';

import { cookies } from 'next/headers';
import { revalidatePath } from 'next/cache';
import { echo, handle, simple, type ActionState } from '@/server/action';
import { recordAudit } from '@/server/audit';
import { flash } from '@/server/flash';
import { requireAdmin, requireUser } from '@/server/session';
import * as telegram from '@/server/telegram';
import { loadUser } from '@/server/users';
import { parseForm, validate } from '@/lib/validate';

// ── Administration ─────────────────────────────────────────────────────────

export async function saveTelegramTokenAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const admin = await requireAdmin();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(input, { telegram_bot_token: ['required', 'string', 'max:255'] });
    const result = await telegram.storeToken(data.telegram_bot_token.trim(), admin);
    await flash(result.ok ? 'success' : 'error', result.message);
    revalidatePath('/settings');
  }, { ...echo(input), telegram_bot_token: '' });
}

export async function testTelegramAction(): Promise<void> {
  await requireAdmin();
  await simple(async () => {
    const result = await telegram.testConnection();
    await flash(result.ok ? 'success' : 'error', result.message);
    revalidatePath('/settings');
  });
}

/** Send a test message to any chat id, or to the administrator's own connected chat. */
export async function sendTelegramTestAction(formData: FormData): Promise<void> {
  const admin = await requireAdmin();
  await simple(async () => {
    const chat = String(formData.get('chat_id') ?? '').trim() || admin.telegramChatId;
    if (!chat) {
      await flash('error', 'Enter a chat id, or connect your own Telegram from your profile first.');
      return;
    }
    const result = await telegram.send(chat, telegram.testMessage());
    await flash(result.ok ? 'success' : 'error', result.ok ? 'Test message sent. Check Telegram.' : result.message);
    revalidatePath('/settings');
  });
}

// ── Personal connection ────────────────────────────────────────────────────

export async function connectTelegramAction(): Promise<void> {
  const user = await requireUser();
  await simple(async () => {
    if (!(await telegram.isReady())) {
      await flash('error', 'Telegram is not set up for this portal yet. Ask an administrator to add the bot in Settings.');
      return;
    }
    const token = await telegram.issueConnectToken(user);
    (await cookies()).set('telegram_connect', token, { path: '/', maxAge: 30 * 60, httpOnly: true, sameSite: 'lax' });
    await flash('success', 'Now open the bot in Telegram and press Start. This page connects automatically.');
    revalidatePath('/profile');
  });
}

/** Look for the person's Start message; used by the button and by the page while it waits. */
export async function confirmTelegram(): Promise<boolean> {
  const user = await requireUser();
  let current = await loadUser(user.id);
  if (!current) return false;
  const pending = !!current.telegramConnectToken;
  if (!current.telegramChatId) {
    await telegram.claimPendingStarts();
    current = await loadUser(user.id);
  }
  if (current?.telegramChatId && pending) {
    (await cookies()).delete('telegram_connect');
    await recordAudit('telegram.connected', { type: 'user', id: user.id }, {}, user.id);
    await telegram.send(current.telegramChatId, telegram.welcomeMessage(current.name));
  }
  return !!current?.telegramChatId;
}

export async function confirmTelegramAction(): Promise<void> {
  await simple(async () => {
    const connected = await confirmTelegram();
    await flash(connected ? 'success' : 'warning', connected ? 'Telegram is connected.' : 'We have not seen your Start message yet. Press Start in the bot, then try again.');
    revalidatePath('/profile');
  });
}

export async function disconnectTelegramAction(): Promise<void> {
  const user = await requireUser();
  await simple(async () => {
    await telegram.disconnect(user);
    (await cookies()).delete('telegram_connect');
    await recordAudit('telegram.disconnected', { type: 'user', id: user.id }, {}, user.id);
    await flash('success', 'Telegram is disconnected.');
    revalidatePath('/profile');
  });
}

export async function testOwnTelegramAction(): Promise<void> {
  const user = await requireUser();
  await simple(async () => {
    if (!user.telegramChatId) {
      await flash('error', 'Connect Telegram first.');
      return;
    }
    const result = await telegram.send(user.telegramChatId, telegram.testMessage());
    await flash(result.ok ? 'success' : 'error', result.ok ? 'Test message sent. Check Telegram.' : result.message);
  });
}
