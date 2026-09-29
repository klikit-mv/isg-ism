import { eq } from 'drizzle-orm';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { db, schema } from '@/db';
import { getSetting } from '@/server/settings';
import { claimPendingStarts, disconnect, issueConnectToken, send, storeToken, telegramChannel } from '@/server/telegram';
import { loadUser } from '@/server/users';
import { makeUser } from '../factories';

type Call = { method: string; body: any };
function fakeTelegram(handlers: Record<string, (body: any) => unknown>) {
  const calls: Call[] = [];
  vi.stubGlobal('fetch', vi.fn(async (url: string, init: RequestInit) => {
    const method = String(url).split('/').pop()!;
    const body = JSON.parse(String(init.body ?? '{}'));
    calls.push({ method, body });
    return new Response(JSON.stringify(handlers[method]?.(body) ?? { ok: true, result: {} }));
  }));
  return calls;
}
afterEach(() => vi.unstubAllGlobals());

describe('telegram', () => {
  it('saves a valid bot token encrypted and remembers the username', async () => {
    fakeTelegram({ getMe: () => ({ ok: true, result: { username: 'scout_bot' } }) });
    expect(await storeToken('123:abc', null)).toEqual({ ok: true, message: 'Bot @scout_bot saved.' });
    expect(await getSetting('telegram_bot_username')).toBe('scout_bot');
    const [row] = await db.select().from(schema.settings).where(eq(schema.settings.key, 'telegram_bot_token'));
    expect(row.value).not.toContain('123:abc');
    expect(await getSetting('telegram_bot_token')).toBe('123:abc');
  });

  it('rejects a token Telegram does not accept and saves nothing', async () => {
    fakeTelegram({ getMe: () => ({ ok: false }) });
    expect((await storeToken('bad', null)).ok).toBe(false);
    expect(await getSetting('telegram_bot_token')).toBeNull();
  });

  it('links a person who sends /start with their one-time code', async () => {
    fakeTelegram({ getMe: () => ({ ok: true, result: { username: 'scout_bot' } }) });
    await storeToken('123:abc', null);
    const user = await makeUser();
    const other = await makeUser();
    const code = await issueConnectToken(user);
    const calls = fakeTelegram({
      getUpdates: () => ({ ok: true, result: [
        { update_id: 10, message: { text: `/start ${code}`, chat: { id: 555 } } },
        { update_id: 11, message: { text: '/start wrongcode', chat: { id: 666 } } },
        { update_id: 12, message: { text: 'hello', chat: { id: 777 } } },
      ] }),
    });
    expect(await claimPendingStarts()).toBe(1);
    const linked = await loadUser(user.id);
    expect(linked?.telegramChatId).toBe('555');
    expect(linked?.telegramNotificationsEnabled).toBe(true);
    expect(linked?.telegramConnectToken).toBeNull();
    expect((await loadUser(other.id))?.telegramChatId).toBeNull();
    expect(await getSetting('telegram_update_offset')).toBe('13');
    expect(calls[0].body.offset).toBe(0);
  });

  it('an expired code does not link', async () => {
    fakeTelegram({ getMe: () => ({ ok: true, result: { username: 'b' } }) });
    await storeToken('123:abc', null);
    const user = await makeUser();
    const code = await issueConnectToken(user);
    await db.update(schema.users).set({ telegramConnectTokenExpiresAt: new Date(Date.now() - 1000) }).where(eq(schema.users.id, user.id));
    fakeTelegram({ getUpdates: () => ({ ok: true, result: [{ update_id: 1, message: { text: `/start ${code}`, chat: { id: 1 } } }] }) });
    expect(await claimPendingStarts()).toBe(0);
  });

  it('sends alerts only to connected people who switched Telegram on', async () => {
    fakeTelegram({ getMe: () => ({ ok: true, result: { username: 'b' } }) });
    await storeToken('123:abc', null);
    const calls = fakeTelegram({});
    const user = await makeUser();
    await telegramChannel(user, { title: 'Hi <b>', body: 'Body', url: null });
    expect(calls).toHaveLength(0);
    await db.update(schema.users).set({ telegramChatId: '42', telegramNotificationsEnabled: true }).where(eq(schema.users.id, user.id));
    await telegramChannel((await loadUser(user.id))!, { title: 'Hi <b>', body: 'Body', url: null });
    expect(calls).toHaveLength(1);
    expect(calls[0].body).toMatchObject({ chat_id: '42', parse_mode: 'HTML' });
    expect(calls[0].body.text).toContain('Hi &lt;b&gt;');
    await db.update(schema.users).set({ telegramNotificationsEnabled: false }).where(eq(schema.users.id, user.id));
    await telegramChannel((await loadUser(user.id))!, { title: 'x', body: 'y', url: null });
    expect(calls).toHaveLength(1);
  });

  it('explains common Telegram errors and disconnecting clears the link', async () => {
    fakeTelegram({ getMe: () => ({ ok: true, result: { username: 'b' } }) });
    await storeToken('123:abc', null);
    fakeTelegram({ sendMessage: () => ({ ok: false, description: 'Bad Request: chat not found' }) });
    expect((await send('1', 'x')).message).toContain('press Start first');
    fakeTelegram({ sendMessage: () => ({ ok: false, description: 'Forbidden: bot was blocked by the user' }) });
    expect((await send('1', 'x')).message).toContain('blocked the bot');
    const user = await makeUser();
    await db.update(schema.users).set({ telegramChatId: '9', telegramNotificationsEnabled: true }).where(eq(schema.users.id, user.id));
    await disconnect(user);
    expect((await loadUser(user.id))?.telegramChatId).toBeNull();
  });
});
