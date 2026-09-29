'use client';

import { useRef } from 'react';
import { loginAction } from '@/app/actions/auth';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Input } from '@/components/form/fields';

interface Demo {
  accounts: { role: string; nationalId: string }[];
  pin: string;
}

export function LoginForm({ next, demo }: { next: string; demo: Demo | null }) {
  const box = useRef<HTMLDivElement>(null);
  const use = (nationalId: string, pin: string) => {
    const form = box.current?.closest('form');
    if (!form) return;
    (form.elements.namedItem('national_id') as HTMLInputElement).value = nationalId;
    const pinInput = form.elements.namedItem('pin') as HTMLInputElement;
    pinInput.value = pin;
    pinInput.focus();
  };
  return (
    <ActionForm action={loginAction}>
      <input type="hidden" name="next" value={next} />
      <Input name="national_id" label="National ID" required autoFocus autoComplete="username" className="input uppercase" />
      <Input name="pin" label="PIN" type="password" required autoComplete="current-password" inputMode="numeric" />
      <SubmitButton className="btn-primary w-full" pendingText="Signing in…">Sign in</SubmitButton>
      {demo && (
        <div ref={box} className="rounded-lg border border-dashed border-gold-400 bg-gold-50 p-4 text-sm dark:border-gold-700 dark:bg-gold-900/20" data-testid="sample-logins">
          <p className="mb-2 font-semibold text-gold-800 dark:text-gold-200">Sample logins (development only)</p>
          <table className="w-full text-left">
            <tbody>
              {demo.accounts.map((a) => (
                <tr key={a.nationalId}>
                  <td className="py-0.5 text-gray-600 dark:text-gray-300">{a.role}</td>
                  <td className="py-0.5 font-mono">{a.nationalId}</td>
                  <td className="py-0.5 text-right"><button type="button" className="link text-xs" onClick={() => use(a.nationalId, demo.pin)}>Use</button></td>
                </tr>
              ))}
            </tbody>
          </table>
          <p className="mt-2 text-gray-600 dark:text-gray-300">PIN for all: <span className="font-mono font-semibold">{demo.pin}</span></p>
        </div>
      )}
    </ActionForm>
  );
}
