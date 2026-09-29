'use client';

import { SubmitButton } from './form/ActionForm';

/** A small upload form: choose a picture, press the button. */
export function PhotoUpload({ action, fields = {}, name = 'photo', label = 'Upload photo', help = 'PNG, JPEG or WebP, up to 5 MB.' }: { action: (formData: FormData) => void | Promise<void>; fields?: Record<string, string>; name?: string; label?: string; help?: string }) {
  return (
    <form action={action} className="space-y-3">
      {Object.entries(fields).map(([k, v]) => <input key={k} type="hidden" name={k} value={v} />)}
      <input name={name} type="file" accept="image/png,image/jpeg,image/webp" required className="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-50 file:px-3 file:py-2 file:text-navy-700 dark:text-gray-300 dark:file:bg-navy-900 dark:file:text-navy-200" />
      <p className="text-xs text-gray-500 dark:text-gray-400">{help}</p>
      <SubmitButton className="btn-primary btn-sm">{label}</SubmitButton>
    </form>
  );
}
