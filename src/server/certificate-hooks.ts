import type { ActivityRow } from './activities';
import type { AuthUser } from './users';

/**
 * Runs after the attendance register is saved. Certificates are issued here
 * once the certificates module exists; failures never undo the saved marks.
 */
export async function afterAttendanceSave(_activity: ActivityRow, _actor: AuthUser): Promise<{ message?: string; error?: string } | null> {
  return null;
}
