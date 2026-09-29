'use server';

import { revalidatePath } from 'next/cache';
import { requireAdmin } from '@/server/session';
import { importLegacy, inspectLegacy, SHEETS, type InspectReport, type LegacyReport } from '@/server/legacy-import';

export interface LegacyState { mode?: 'inspect' | 'dry' | 'import'; inspect?: InspectReport; report?: LegacyReport; error?: string }

const MAX_BYTES = 20 * 1024 * 1024;

/** Inspect the workbook, try it as a dry run, or import it for real. */
export async function legacyImportAction(_: LegacyState | null, formData: FormData): Promise<LegacyState> {
  const admin = await requireAdmin();
  const mode = formData.get('mode') === 'import' ? 'import' : formData.get('mode') === 'dry' ? 'dry' : 'inspect';
  const file = formData.get('file');
  if (!(file instanceof File) || file.size === 0) return { mode, error: 'Choose an Excel (.xlsx) file.' };
  if (file.size > MAX_BYTES) return { mode, error: 'The file is larger than 20 MB.' };
  if (!file.name.toLowerCase().endsWith('.xlsx')) return { mode, error: 'The file must be an .xlsx workbook.' };
  const bytes = Buffer.from(await file.arrayBuffer());
  const only = String(formData.get('sheet') ?? '') || null;
  try {
    if (mode === 'inspect') return { mode, inspect: await inspectLegacy(bytes) };
    const report = await importLegacy(bytes, { dryRun: mode === 'dry', actor: admin, onlySheet: only && Object.keys(SHEETS).includes(only) ? only : null });
    if (mode === 'import') revalidatePath('/', 'layout');
    return { mode, report };
  } catch (e) {
    return { mode, error: `The workbook could not be imported: ${e instanceof Error ? e.message : 'unknown error'}` };
  }
}
