import { createHash, randomBytes } from 'node:crypto';
import { and, eq, gt } from 'drizzle-orm';
import { db, schema } from '@/db';
import { config } from '@/lib/config';
import { recordAudit } from './audit';
import type { AlertData } from './notifications';
import { getSetting, setSetting } from './settings';
import type { AuthUser } from './users';

const API = 'https://api.telegram.org';

interface TelegramResult { ok: boolean; result?: any; description?: string }

async function call(token: string, method: string, payload: Record<string, unknown> = {}): Promise<TelegramResult> {
  try {
    const response = await fetch(`${API}/bot${token}/${method}`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload), signal: AbortSignal.timeout(20_000),
    });
    return (await response.json()) as TelegramResult;
  } catch (error) {
    console.warn('Telegram request failed', method, (error as Error).message);
    return { ok: false };
  }
}

export const botToken = () => getSetting('telegram_bot_token');
export const botUsername = () => getSetting('telegram_bot_username');
export const isConfigured = async () => !!(await botToken());
export const isReady = async () => !!(await botToken()) && !!(await botUsername());

export const deepLink = async (token: string): Promise<string | null> => {
  const username = await botUsername();
  return username ? `https://t.me/${username}?start=${token}` : null;
};

export async function storeToken(token: string, actor: AuthUser | null): Promise<{ ok: boolean; message: string }> {
  const result = await call(token, 'getMe');
  if (!result.ok) return { ok: false, message: 'Telegram did not accept that bot token.' };
  await setSetting('telegram_bot_token', token, actor?.id ?? null);
  await setSetting('telegram_bot_username', String(result.result?.username ?? ''), actor?.id ?? null);
  await setSetting('telegram_update_offset', '0', actor?.id ?? null);
  await recordAudit('settings.telegram_saved', null, { bot: result.result?.username }, actor?.id ?? null);
  return { ok: true, message: `Bot @${result.result?.username ?? ''} saved.` };
}

export async function testConnection(): Promise<{ ok: boolean; message: string }> {
  const token = await botToken();
  if (!token) return { ok: false, message: 'No Telegram bot token is saved yet.' };
  const result = await call(token, 'getMe');
  return result.ok
    ? { ok: true, message: `Telegram is reachable. Bot @${result.result?.username ?? ''} is ready.` }
    : { ok: false, message: 'Telegram could not be reached with the saved token.' };
}

const hashToken = (token: string) => createHash('sha256').update(token).digest('hex');

/** A one-time code the person sends to the bot with /start. Valid for 30 minutes. */
export async function issueConnectToken(user: Pick<AuthUser, 'id'>): Promise<string> {
  const token = randomBytes(24).toString('base64url').slice(0, 32);
  await db.update(schema.users).set({ telegramConnectToken: hashToken(token), telegramConnectTokenExpiresAt: new Date(Date.now() + 30 * 60_000), updatedAt: new Date() }).where(eq(schema.users.id, user.id));
  return token;
}

/** Read /start messages sent to the bot (getUpdates, so the bot must not have a webhook) and link the senders. */
export async function claimPendingStarts(): Promise<number> {
  const token = await botToken();
  if (!token) return 0;
  const offset = Number(await getSetting('telegram_update_offset', '0'));
  const result = await call(token, 'getUpdates', { offset, timeout: 0 });
  if (!result.ok) return 0;
  let linked = 0;
  let maxId = offset - 1;
  for (const update of (result.result ?? []) as any[]) {
    maxId = Math.max(maxId, Number(update.update_id ?? 0));
    const text = String(update.message?.text ?? '');
    const chatId = update.message?.chat?.id;
    const match = /^\/start\s+(\S+)/.exec(text);
    if (chatId === undefined || chatId === null || !match) continue;
    const [user] = await db.select({ id: schema.users.id }).from(schema.users)
      .where(and(eq(schema.users.telegramConnectToken, hashToken(match[1])), gt(schema.users.telegramConnectTokenExpiresAt, new Date()))).limit(1);
    if (!user) continue;
    await db.update(schema.users).set({ telegramChatId: String(chatId), telegramNotificationsEnabled: true, telegramConnectToken: null, telegramConnectTokenExpiresAt: null, updatedAt: new Date() }).where(eq(schema.users.id, user.id));
    linked++;
  }
  if (maxId >= offset) await setSetting('telegram_update_offset', String(maxId + 1));
  return linked;
}

const escapeHtml = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export async function send(chatId: string, html: string): Promise<{ ok: boolean; message: string }> {
  const token = await botToken();
  if (!token) return { ok: false, message: 'No Telegram bot token is saved yet.' };
  const result = await call(token, 'sendMessage', { chat_id: chatId, text: html, parse_mode: 'HTML', disable_web_page_preview: true });
  if (result.ok) return { ok: true, message: 'Message sent.' };
  const description = result.description ?? '';
  if (description.includes('chat not found')) return { ok: false, message: 'Telegram does not know that chat. The person must open the bot and press Start first.' };
  if (description.includes('blocked by the user')) return { ok: false, message: 'That person has blocked the bot.' };
  return { ok: false, message: description ? `Telegram refused the message: ${description}` : 'Telegram could not be reached.' };
}

export async function disconnect(user: Pick<AuthUser, 'id'>): Promise<void> {
  await db.update(schema.users).set({ telegramChatId: null, telegramNotificationsEnabled: false, telegramConnectToken: null, telegramConnectTokenExpiresAt: null, updatedAt: new Date() }).where(eq(schema.users.id, user.id));
}

export const welcomeMessage = (name: string) => `✅ <b>${escapeHtml(config.shortName)}</b>\nHello ${escapeHtml(name)}, your account is connected. Notifications will arrive here.`;
export const testMessage = () => `🔔 Test message from <b>${escapeHtml(config.shortName)}</b>. Notifications are working.`;

/** Notification channel: forwards each alert to people who connected Telegram and switched it on. */
export async function telegramChannel(user: AuthUser, alert: AlertData): Promise<void> {
  if (!user.telegramChatId || !user.telegramNotificationsEnabled || !(await isConfigured())) return;
  const base = process.env.APP_URL?.replace(/\/$/, '');
  const link = alert.url && base ? `\n${base}${alert.url}` : '';
  await send(user.telegramChatId, `<b>${escapeHtml(alert.title)}</b>\n${escapeHtml(alert.body)}${link}`);
}
