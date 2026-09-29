'use client';

import { createContext, useActionState, useContext } from 'react';
import { useFormStatus } from 'react-dom';
import type { ActionState } from '@/server/action';

export type FormAction = (prev: ActionState | null, formData: FormData) => Promise<ActionState | null>;

const FormContext = createContext<ActionState | null>(null);
export const useFormState = () => useContext(FormContext);

/** A form bound to a server action; shows field errors and keeps what was typed. */
export function ActionForm({ action, children, className = 'space-y-4', encType, id }: { action: FormAction; children: React.ReactNode; className?: string; encType?: string; id?: string }) {
  const [state, formAction] = useActionState(action, null);
  const summary = state?.fields ? Object.values(state.fields) : state?.error ? [state.error] : [];
  return (
    <FormContext.Provider value={state}>
      <form id={id} action={formAction} className={className} encType={encType}>
        {summary.length > 0 && (
          <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200" role="alert">
            {state?.fields ? <p className="font-medium">Please check the form.</p> : null}
            <ul className={state?.fields ? 'mt-1 list-inside list-disc' : ''}>
              {summary.map((m, i) => <li key={i} className={state?.fields ? '' : 'list-none'}>{m}</li>)}
            </ul>
          </div>
        )}
        {children}
      </form>
    </FormContext.Provider>
  );
}

/** Submit button that disables itself while the action runs. */
export function SubmitButton({ children, className = 'btn-primary', pendingText }: { children: React.ReactNode; className?: string; pendingText?: string }) {
  const { pending } = useFormStatus();
  return (
    <button type="submit" className={className} disabled={pending} aria-busy={pending}>
      {pending && pendingText ? pendingText : children}
    </button>
  );
}
