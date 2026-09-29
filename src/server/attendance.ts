import { and, eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import { AttendanceStatus } from '@/lib/enums';
import { recordAudit } from './audit';
import { markableStudentIds, verifyCanManageAttendance, type ActivityRow } from './activities';
import { settleRosterPayment, syncForAttendance } from './class-fees';
import { NotAccessibleError } from './errors';
import type { AuthUser } from './users';

export interface Mark {
  status?: string | null;
  remarks?: string | null;
  payment?: string | null;
}

/**
 * Upsert marks (blank statuses are skipped), sync class fees and record roster
 * cash in one transaction. Any scout outside the roster or the actor's scope
 * fails the whole save.
 */
export async function markAttendance(activity: ActivityRow, actor: AuthUser, marks: Record<string, Mark>): Promise<{ marked: number }> {
  await verifyCanManageAttendance(activity, actor);
  const allowed = await markableStudentIds(activity, actor);

  return db.transaction(async (tx) => {
    let marked = 0;
    const R = schema.attendanceRecords;
    for (const [studentIdKey, mark] of Object.entries(marks)) {
      const status = mark.status ?? '';
      if (!AttendanceStatus.is(status)) continue;
      const studentId = Number(studentIdKey);
      if (!allowed.includes(studentId)) throw new NotAccessibleError('One of the scouts is not on this roster or not in your groups. Nothing was saved.');

      const [student] = await tx.select({ id: schema.students.id, uuid: schema.students.uuid }).from(schema.students).where(eq(schema.students.id, studentId));
      const now = new Date();
      const remarks = mark.remarks ? String(mark.remarks).slice(0, 255) : null;
      const [existing] = await tx.select({ id: R.id }).from(R).where(and(eq(R.activityId, activity.id), eq(R.studentId, studentId)));
      if (existing) await tx.update(R).set({ status, remarks, markedBy: actor.id, markedAt: now, updatedAt: now }).where(eq(R.id, existing.id));
      else await tx.insert(R).values({ activityId: activity.id, studentId, status, remarks, markedBy: actor.id, markedAt: now, createdAt: now, updatedAt: now });

      const fee = await syncForAttendance(activity, student, status, actor, tx);
      if (fee && status !== 'Excused' && mark.payment !== undefined && mark.payment !== null && mark.payment !== '') {
        await settleRosterPayment(fee, String(mark.payment), actor, tx);
      }
      await recordAudit('attendance.marked', { type: 'activity', id: activity.uuid }, { student: student.uuid, status }, actor.id, tx);
      marked++;
    }
    return { marked };
  });
}
