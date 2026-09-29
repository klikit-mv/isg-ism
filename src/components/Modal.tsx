'use client';

import { useEffect, useState } from 'react';

const maxWidths = { sm: 'sm:max-w-sm', md: 'sm:max-w-md', lg: 'sm:max-w-lg', xl: 'sm:max-w-xl', '2xl': 'sm:max-w-2xl', '4xl': 'sm:max-w-4xl' } as const;

export const openModal = (name: string) => window.dispatchEvent(new CustomEvent('open-modal', { detail: name }));
export const closeModal = (name: string) => window.dispatchEvent(new CustomEvent('close-modal', { detail: name }));

/** A dialog opened by name from any `ModalButton`. */
export function Modal({ name, title, maxWidth = '2xl', children, open = false }: { name: string; title?: string; maxWidth?: keyof typeof maxWidths; children: React.ReactNode; open?: boolean }) {
  const [show, setShow] = useState(open);
  useEffect(() => {
    const on = (e: Event) => (e as CustomEvent).detail === name && setShow(true);
    const off = (e: Event) => (e as CustomEvent).detail === name && setShow(false);
    const esc = (e: KeyboardEvent) => e.key === 'Escape' && setShow(false);
    window.addEventListener('open-modal', on);
    window.addEventListener('close-modal', off);
    window.addEventListener('keydown', esc);
    return () => {
      window.removeEventListener('open-modal', on);
      window.removeEventListener('close-modal', off);
      window.removeEventListener('keydown', esc);
    };
  }, [name]);
  useEffect(() => {
    document.body.classList.toggle('overflow-y-hidden', show);
    return () => document.body.classList.remove('overflow-y-hidden');
  }, [show]);
  if (!show) return null;
  return (
    <div className="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" role="dialog" aria-modal="true">
      <div className="fixed inset-0 bg-gray-900/60" onClick={() => setShow(false)} />
      <div className={`relative mx-auto mt-10 w-full overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-800 ${maxWidths[maxWidth]}`}>
        {title ? (
          <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
            <button type="button" className="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" onClick={() => setShow(false)} aria-label="Close">
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
        ) : null}
        <div className="px-5 py-4">{children}</div>
      </div>
    </div>
  );
}

export function ModalButton({ name, children, className = 'btn-primary' }: { name: string; children: React.ReactNode; className?: string }) {
  return <button type="button" className={className} onClick={() => openModal(name)}>{children}</button>;
}

export function CancelButton({ name, children = 'Cancel' }: { name: string; children?: React.ReactNode }) {
  return <button type="button" className="btn-secondary" onClick={() => closeModal(name)}>{children}</button>;
}
