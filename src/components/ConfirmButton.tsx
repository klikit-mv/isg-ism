'use client';

import { useId } from 'react';
import { Modal, ModalButton, CancelButton } from './Modal';
import { SubmitButton } from './form/ActionForm';

/**
 * A button that opens a confirmation dialog and then runs a server action.
 * `fields` become hidden inputs; `children` may add extra inputs (e.g. a reason).
 */
export function ConfirmButton({
  action, label, title = 'Please confirm', message = 'Are you sure?', confirm = 'Confirm', variant = 'danger', size = 'sm', fields = {}, children,
}: {
  action: (formData: FormData) => void | Promise<void>;
  label: string;
  title?: string;
  message?: string;
  confirm?: string;
  variant?: 'danger' | 'primary' | 'secondary' | 'accent';
  size?: 'sm' | 'md';
  fields?: Record<string, string>;
  children?: React.ReactNode;
}) {
  const name = `confirm-${useId()}`;
  return (
    <>
      <ModalButton name={name} className={`btn-${variant} ${size === 'sm' ? 'btn-sm' : ''}`}>{label}</ModalButton>
      <Modal name={name} title={title} maxWidth="md">
        <form action={action} className="space-y-4">
          {Object.entries(fields).map(([k, v]) => <input key={k} type="hidden" name={k} value={v} />)}
          <p className="text-sm text-gray-600 dark:text-gray-300">{message}</p>
          {children}
          <div className="flex justify-end gap-2">
            <CancelButton name={name} />
            <SubmitButton className={`btn-${variant}`}>{confirm}</SubmitButton>
          </div>
        </form>
      </Modal>
    </>
  );
}

/** A one-click button that runs a server action (no dialog). */
export function ActionButton({ action, label, variant = 'secondary', size = 'sm', fields = {} }: { action: (formData: FormData) => void | Promise<void>; label: string; variant?: 'danger' | 'primary' | 'secondary' | 'accent'; size?: 'sm' | 'md'; fields?: Record<string, string> }) {
  return (
    <form action={action}>
      {Object.entries(fields).map(([k, v]) => <input key={k} type="hidden" name={k} value={v} />)}
      <SubmitButton className={`btn-${variant} ${size === 'sm' ? 'btn-sm' : ''}`}>{label}</SubmitButton>
    </form>
  );
}
