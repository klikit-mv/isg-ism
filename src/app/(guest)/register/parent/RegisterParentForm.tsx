'use client';

import { useState, useTransition } from 'react';
import { lookupChildAction, registerParentAction, type ChildLookup } from '@/app/actions/auth';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Input } from '@/components/form/fields';

interface Child {
  nationalId: string;
  name: string;
  section: string;
}

/** Live child lookup: type a National ID, the scout appears, and is sent with the form. */
function ChildrenLookup() {
  const [query, setQuery] = useState('');
  const [children, setChildren] = useState<Child[]>([]);
  const [message, setMessage] = useState<string | null>(null);
  const [, start] = useTransition();

  const lookup = (value: string) => {
    setQuery(value);
    setMessage(null);
    if (value.trim().length < 4) return;
    start(async () => {
      const result: ChildLookup = await lookupChildAction(value, children.map((c) => c.nationalId));
      if (result.child) {
        setChildren((prev) => [...prev, result.child!]);
        setQuery('');
      }
      setMessage(result.message ?? null);
    });
  };

  return (
    <div>
      <label htmlFor="child_lookup" className="label">Children (National ID)</label>
      <input id="child_lookup" value={query} onChange={(e) => lookup(e.target.value)} className="input uppercase" placeholder="Type your child's National ID" autoComplete="off" />
      {message && <p className="mt-1 text-xs text-amber-700 dark:text-amber-300">{message}</p>}
      <ul className="mt-2 space-y-2">
        {children.map((c) => (
          <li key={c.nationalId} className="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
            <span>{c.name} <span className="text-gray-500">({c.section}, {c.nationalId})</span></span>
            <input type="hidden" name="children[]" value={c.nationalId} />
            <button type="button" className="link text-xs" onClick={() => setChildren(children.filter((x) => x.nationalId !== c.nationalId))}>Remove</button>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function RegisterParentForm() {
  return (
    <ActionForm action={registerParentAction} className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <Input name="name" label="Full name" required />
        <Input name="national_id" label="National ID" required className="input uppercase" />
        <Input name="email" label="Email" type="email" required />
      </div>
      <ChildrenLookup />
      <div className="grid gap-4 sm:grid-cols-2">
        <Input name="pin" label="Choose a PIN" type="password" required help="4 to 32 characters." inputMode="numeric" />
        <Input name="pin_confirmation" label="Confirm PIN" type="password" required inputMode="numeric" />
      </div>
      <SubmitButton className="btn-primary w-full" pendingText="Registering…">Register</SubmitButton>
    </ActionForm>
  );
}
