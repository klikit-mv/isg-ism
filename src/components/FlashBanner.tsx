'use client';

import { useEffect, useState } from 'react';
import type { Flash } from '@/server/flash';

const styles = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200',
  error: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200',
  warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
} as const;

/** Shows the flash message the server sent, then clears its cookie. */
export function FlashBanner({ flash }: { flash: Flash | null }) {
  const [open, setOpen] = useState(true);
  useEffect(() => {
    if (flash) {
      document.cookie = 'flash=; Max-Age=0; path=/';
      setOpen(true);
    }
  }, [flash]);
  if (!flash || !open) return null;
  return (
    <div className={`mb-4 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm ${styles[flash.kind]}`} role="alert">
      <div>{flash.message}</div>
      <button type="button" onClick={() => setOpen(false)} className="opacity-60 hover:opacity-100" aria-label="Dismiss">&times;</button>
    </div>
  );
}
