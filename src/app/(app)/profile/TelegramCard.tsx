import { ActionButton } from '@/components/ConfirmButton';
import { connectTelegramAction, confirmTelegramAction, disconnectTelegramAction, testOwnTelegramAction } from '@/app/actions/telegram';
import { deepLink, isReady } from '@/server/telegram';
import { cookies } from 'next/headers';
import type { AuthUser } from '@/server/users';
import { TelegramPoller } from './TelegramPoller';

/** Connect the account to Telegram so alerts also arrive there. */
export async function TelegramCard({ user }: { user: AuthUser }) {
  const ready = await isReady();
  const connected = !!user.telegramChatId;
  const pending = !connected && !!user.telegramConnectToken && !!user.telegramConnectTokenExpiresAt && user.telegramConnectTokenExpiresAt > new Date();
  const code = pending ? (await cookies()).get('telegram_connect')?.value : undefined;
  const link = code ? await deepLink(code) : null;
  return (
    <section className="card">
      <h2 className="mb-2 text-lg font-semibold">Telegram</h2>
      {!ready ? (
        <p className="text-sm text-gray-500 dark:text-gray-400">Telegram is not set up for this portal yet. An administrator adds the bot in Settings.</p>
      ) : connected ? (
        <div className="space-y-3">
          <p className="text-sm text-emerald-700 dark:text-emerald-300">Connected. Alerts are sent to your Telegram when the option above is on.</p>
          <div className="flex flex-wrap gap-2">
            <ActionButton action={testOwnTelegramAction} label="Send test message" variant="accent" size="md" />
            <ActionButton action={disconnectTelegramAction} label="Disconnect" variant="danger" size="md" />
          </div>
        </div>
      ) : pending ? (
        <div className="space-y-3">
          <p className="text-sm text-gray-600 dark:text-gray-300">Open the bot in Telegram and press <strong>Start</strong>. This page connects automatically.</p>
          <div className="flex flex-wrap gap-2">
            {link && <a href={link} target="_blank" rel="noopener" className="btn-primary">Open Telegram</a>}
            <ActionButton action={confirmTelegramAction} label="I pressed Start" size="md" />
          </div>
          <TelegramPoller />
        </div>
      ) : (
        <div className="space-y-3">
          <p className="text-sm text-gray-600 dark:text-gray-300">Get your alerts in Telegram as well as in the portal.</p>
          <ActionButton action={connectTelegramAction} label="Connect Telegram" variant="primary" size="md" />
        </div>
      )}
    </section>
  );
}
