'use client';

import { saveTelegramTokenAction, sendTelegramTestAction, testTelegramAction } from '@/app/actions/telegram';
import { ActionButton } from '@/components/ConfirmButton';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Input } from '@/components/form/fields';

export function TelegramSettings({ configured, username }: { configured: boolean; username: string | null }) {
  return (
    <section className="card space-y-4">
      <div>
        <h2 className="text-lg font-semibold">Telegram</h2>
        <p className="text-sm text-gray-500 dark:text-gray-400">
          Create a bot with @BotFather and paste its token here. It is stored encrypted and never shown again. The bot must not have a webhook: connecting uses getUpdates.
        </p>
      </div>
      <p className="text-sm">{configured ? <>Bot saved{username ? <> — <strong>@{username}</strong></> : null}.</> : 'No bot saved yet.'}</p>
      <ActionForm action={saveTelegramTokenAction} className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="flex-1"><Input name="telegram_bot_token" label="Bot token" type="password" autoComplete="off" placeholder={configured ? 'Paste a new token to replace it' : '123456:ABC…'} /></div>
        <SubmitButton>Save bot</SubmitButton>
      </ActionForm>
      {configured && (
        <div className="flex flex-col gap-3 border-t border-gray-200 pt-4 dark:border-gray-700 sm:flex-row sm:items-end">
          <ActionButton action={testTelegramAction} label="Test connection" size="md" />
          <form action={sendTelegramTestAction} className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end">
            <div className="flex-1">
              <label className="label" htmlFor="chat_id">Send a test message</label>
              <input id="chat_id" name="chat_id" className="input" placeholder="Chat id (empty: your own connected Telegram)" />
            </div>
            <button type="submit" className="btn-accent">Send</button>
          </form>
        </div>
      )}
    </section>
  );
}
