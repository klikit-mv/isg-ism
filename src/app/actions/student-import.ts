'use server';

import { forbidden } from 'next/navigation';
import { revalidatePath } from 'next/cache';
import { canManageStudents } from '@/server/policies';
import { requireUser } from '@/server/session';
import { processStudentImport, type ImportReport } from '@/server/student-import';

export interface ImportState { mode?: 'check' | 'import'; report?: ImportReport; error?: string }

const MAX_BYTES = 5 * 1024 * 1024;

/** "Check file" reads it without saving anything; "Import" enrols the scouts. */
export async function importStudentsAction(_: ImportState | null, formData: FormData): Promise<ImportState> {
  const user = await requireUser();
  if (!canManageStudents(user)) forbidden();
  const mode = formData.get('mode') === 'import' ? 'import' : 'check';
  const file = formData.get('file');
  if (!(file instanceof File) || file.size === 0) return { mode, error: 'Choose an Excel (.xlsx) or CSV file.' };
  if (file.size > MAX_BYTES) return { mode, error: 'The file is larger than 5 MB.' };
  const name = file.name.toLowerCase();
  const format = name.endsWith('.csv') ? 'csv' : name.endsWith('.xlsx') ? 'xlsx' : null;
  if (!format) return { mode, error: 'The file must be .xlsx or .csv.' };
  try {
    const report = await processStudentImport(Buffer.from(await file.arrayBuffer()), mode === 'import' ? user : null, format);
    if (mode === 'import') revalidatePath('/students');
    return { mode, report };
  } catch {
    return { mode, error: 'That file could not be read. Use the template and save it as .xlsx or .csv.' };
  }
}
