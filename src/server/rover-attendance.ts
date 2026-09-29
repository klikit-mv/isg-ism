import { and, asc, eq, inArray } from 'drizzle-orm';
import { db, schema } from '@/db';
import { RoverAttendanceStatus } from '@/lib/enums';
import { resolveStudentIds, verifyCanManageAttendance, type ActivityRow } from './activities';
import { recordAudit } from './audit';
import { NotAccessibleError, ScoutError } from './errors';
import type { AuthUser } from './users';

type Student = typeof schema.students.$inferSelect;

/** Separate Rover register. Rover attendance never creates fees. */
export async function participants(activity: Pick<ActivityRow, 'id' | 'allStudents'>) {
  const S = schema.students;
  const activeRovers = await db.select().from(S).where(and(eq(S.section, 'Rover'), eq(S.status, 'active'))).orderBy(asc(S.name));
  const rosterIds = new Set(await resolveStudentIds(activity));
  const required = activeRovers.filter((s) => rosterIds.has(s.id));

  const assistants = await db.select({ id: schema.groupAssistantLeaders.studentId }).from(schema.groupAssistantLeaders)
    .innerJoin(schema.activityGroups, eq(schema.activityGroups.groupId, schema.groupAssistantLeaders.groupId))
    .where(eq(schema.activityGroups.activityId, activity.id));
  const assistantIds = new Set(assistants.map((r) => r.id));
  const requiredIds = new Set(required.map((s) => s.id));
  const optional = activeRovers.filter((s) => assistantIds.has(s.id) && !requiredIds.has(s.id));

  const recorded = (await db.select({ id: schema.roverAttendanceRecords.studentId }).from(schema.roverAttendanceRecords).where(eq(schema.roverAttendanceRecords.activityId, activity.id))).map((r) => r.id);
  const listed = new Set([...required, ...optional].map((s) => s.id));
  const extraIds = recorded.filter((id) => !listed.has(id));
  const additional: Student[] = extraIds.length ? await db.select().from(S).where(and(inArray(S.id, extraIds), eq(S.section, 'Rover'))).orderBy(asc(S.name)) : [];
  const taken = new Set([...listed, ...additional.map((s) => s.id)]);
  const available = activeRovers.filter((s) => !taken.has(s.id));
  return { required, optional, additional, available };
}

/** Mark Rovers. Optional Rovers can only be Present. `marks` maps student id to status. */
export async function markRovers(activity: ActivityRow, actor: AuthUser, marks: Record<string, string>): Promise<{ marked: number }> {
  await verifyCanManageAttendance(activity, actor);
  const p = await participants(activity);
  const requiredIds = new Set(p.required.map((s) => s.id));
  const known = new Set([...p.required, ...p.optional, ...p.additional, ...p.available].map((s) => s.id));

  return db.transaction(async (tx) => {
    let marked = 0;
    const R = schema.roverAttendanceRecords;
    for (const [key, value] of Object.entries(marks)) {
      if (!RoverAttendanceStatus.is(value)) continue;
      const studentId = Number(key);
      if (!known.has(studentId)) throw new NotAccessibleError('One of the selected people is not an active Rover. Nothing was saved.');
      const isRequired = requiredIds.has(studentId);
      if (!isRequired && value !== 'Present') throw new ScoutError('Optional Rovers can only be marked Present.');

      const now = new Date();
      const [existing] = await tx.select({ id: R.id }).from(R).where(and(eq(R.activityId, activity.id), eq(R.studentId, studentId)));
      if (existing) await tx.update(R).set({ status: value, isRequired, markedBy: actor.id, markedAt: now, updatedAt: now }).where(eq(R.id, existing.id));
      else await tx.insert(R).values({ activityId: activity.id, studentId, status: value, isRequired, markedBy: actor.id, markedAt: now, createdAt: now, updatedAt: now });
      await recordAudit('rover_attendance.marked', { type: 'activity', id: activity.uuid }, { student_id: studentId, status: value, required: isRequired }, actor.id, tx);
      marked++;
    }
    return { marked };
  });
}

export async function roverMarks(activityId: number): Promise<Record<number, string>> {
  const rows = await db.select({ id: schema.roverAttendanceRecords.studentId, s: schema.roverAttendanceRecords.status }).from(schema.roverAttendanceRecords).where(eq(schema.roverAttendanceRecords.activityId, activityId));
  return Object.fromEntries(rows.map((r) => [r.id, r.s]));
}
