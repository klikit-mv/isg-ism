'use client';

import { changePinAction, updateProfileAction } from '@/app/actions/auth';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Checkbox, Input } from '@/components/form/fields';
import { ThemeChoice } from '@/components/ThemeSwitch';

export function ProfileForm({ user }: { user: { name: string; email: string | null; emailNotifications: boolean; telegramConnected: boolean; telegramNotifications: boolean } }) {
  return (
    <ActionForm action={updateProfileAction}>
      <Input name="name" label="Name" defaultValue={user.name} required />
      <Input name="email" label="Email" type="email" defaultValue={user.email} />
      <div className="space-y-2">
        <Checkbox name="email_notifications_enabled" label="Email me notifications" defaultChecked={user.emailNotifications} />
        {user.telegramConnected && (
          <div><Checkbox name="telegram_notifications_enabled" label="Send notifications to Telegram" defaultChecked={user.telegramNotifications} /></div>
        )}
      </div>
      <SubmitButton>Save</SubmitButton>
    </ActionForm>
  );
}

export function PinForm() {
  return (
    <ActionForm action={changePinAction}>
      <Input name="current_pin" label="Current PIN" type="password" inputMode="numeric" autoComplete="current-password" />
      <Input name="pin" label="New PIN" type="password" inputMode="numeric" autoComplete="new-password" />
      <Input name="pin_confirmation" label="Confirm new PIN" type="password" inputMode="numeric" autoComplete="new-password" />
      <SubmitButton>Change PIN</SubmitButton>
    </ActionForm>
  );
}

export function AppearanceCard() {
  return (
    <section className="card">
      <h2 className="mb-2 text-lg font-semibold">Appearance</h2>
      <p className="mb-3 text-sm text-gray-500 dark:text-gray-400">Choose light or dark mode for this device.</p>
      <ThemeChoice />
    </section>
  );
}
