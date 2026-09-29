import type { ActivityRow } from './activities';
import { issueForActivityAttendance } from './certificates';
import type { AuthUser } from './users';

/**
 * Runs after the attendance register is saved: issues the activity's certificates to
 * present and late scouts. Failures never undo the saved marks; they are reported.
 */
export async function afterAttendanceSave(activity: ActivityRow, actor: AuthUser): Promise<{ message?: string; error?: string } | null> {
  if (activity.certificateTemplateId === null) return null;
  try {
    const { issued, failed } = await issueForActivityAttendance(activity, actor);
    const failures = Object.keys(failed).length;
    return {
      ...(issued > 0 ? { message: `${issued} certificate(s) issued.` } : {}),
      ...(failures > 0 ? { error: `${failures} certificate(s) could not be issued. Retry with Issue on the Certificates page.` } : {}),
    };
  } catch {
    return { error: 'Attendance was saved but the certificates could not be issued. Retry with Issue on the Certificates page.' };
  }
}
