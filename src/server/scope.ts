import { and, eq, inArray, isNull, or, sql, type SQL } from 'drizzle-orm';
import type { AnyMySqlColumn } from 'drizzle-orm/mysql-core';
import { db, schema } from '@/db';
import { isAdmin, isLeader, hasPermission, type AuthUser } from './users';

/**
 * The single place where group-scoped visibility is computed.
 * Admin sees everything; a leader sees the members of the groups they lead
 * (and every pending registration); parents see approved children; scouts see themselves.
 */

/** Admin: all groups; leader: led groups; others: none. */
export async function leaderGroupIds(user: AuthUser): Promise<number[]> {
  if (isAdmin(user)) {
    const rows = await db.select({ id: schema.groups.id }).from(schema.groups).where(isNull(schema.groups.deletedAt));
    return rows.map((r) => r.id);
  }
  if (!isLeader(user)) return [];
  const rows = await db.select({ id: schema.groupLeaders.groupId }).from(schema.groupLeaders)
    .innerJoin(schema.groups, eq(schema.groups.id, schema.groupLeaders.groupId))
    .where(and(isNull(schema.groups.deletedAt), eq(schema.groupLeaders.userId, user.id)));
  return [...new Set(rows.map((r) => r.id))];
}

/**
 * Admin: null (meaning every student). Otherwise the union of led-group
 * members, approved children and the user's own student record.
 */
export async function leaderStudentIds(user: AuthUser): Promise<number[] | null> {
  if (isAdmin(user)) return null;
  const ids = new Set<number>();
  const groupIds = await leaderGroupIds(user);
  if (groupIds.length) {
    const rows = await db.select({ id: schema.groupMembers.studentId }).from(schema.groupMembers).where(inArray(schema.groupMembers.groupId, groupIds));
    rows.forEach((r) => ids.add(r.id));
  }
  const children = await db.select({ id: schema.parentStudentLinks.studentId }).from(schema.parentStudentLinks)
    .where(and(eq(schema.parentStudentLinks.parentUserId, user.id), eq(schema.parentStudentLinks.status, 'approved')));
  children.forEach((r) => ids.add(r.id));
  if (user.studentId) ids.add(user.studentId);
  return [...ids];
}

export async function canAccessGroup(user: AuthUser, groupId: number): Promise<boolean> {
  return isAdmin(user) || (await leaderGroupIds(user)).includes(groupId);
}

export async function canAccessStudent(user: AuthUser, student: { id: number; status: string }): Promise<boolean> {
  if (isAdmin(user)) return true;
  if (isLeader(user) && student.status === 'pending') return true;
  return ((await leaderStudentIds(user)) ?? []).includes(student.id);
}

/** Same as `canAccessStudent` but for a student id (loads it). */
export async function canAccessStudentId(user: AuthUser, studentId: number): Promise<boolean> {
  if (isAdmin(user)) return true;
  const [s] = await db.select({ id: schema.students.id, status: schema.students.status }).from(schema.students).where(eq(schema.students.id, studentId)).limit(1);
  return !!s && canAccessStudent(user, s);
}

/**
 * Condition for queries on `students`: undefined means "no restriction".
 * Leaders also see every pending registration.
 */
export async function studentScope(user: AuthUser): Promise<SQL | undefined> {
  const ids = await leaderStudentIds(user);
  if (ids === null) return undefined;
  const own = inArray(schema.students.id, ids.length ? ids : [0]);
  return isLeader(user) ? or(own, eq(schema.students.status, 'pending')) : own;
}

/** Restrict any query with a student id column to accessible students. */
export async function studentIdScope(user: AuthUser, column: AnyMySqlColumn): Promise<SQL | undefined> {
  const ids = await leaderStudentIds(user);
  return ids === null ? undefined : inArray(column, ids.length ? ids : [0]);
}

/**
 * Leader with at least one group: all-student activities, activities targeting
 * one of their groups, or a section any of their members is in.
 */
export async function activityScope(user: AuthUser): Promise<SQL | undefined> {
  if (isAdmin(user)) return undefined;
  const groupIds = await leaderGroupIds(user);
  if (!groupIds.length) return sql`1 = 0`;

  const sectionRows = await db.selectDistinct({ section: schema.students.section }).from(schema.groupMembers)
    .innerJoin(schema.students, eq(schema.students.id, schema.groupMembers.studentId))
    .where(and(inArray(schema.groupMembers.groupId, groupIds), isNull(schema.students.deletedAt)));
  const sections = sectionRows.map((r) => r.section);

  const byGroup = sql`exists (select 1 from activity_groups ag where ag.activity_id = ${schema.activities.id} and ag.group_id in (${sql.join(groupIds.map((g) => sql`${g}`), sql`, `)}))`;
  const bySection = sections.length
    ? sql`exists (select 1 from activity_sections a_s where a_s.activity_id = ${schema.activities.id} and a_s.section in (${sql.join(sections.map((s) => sql`${s}`), sql`, `)}))`
    : sql`1 = 0`;
  return or(eq(schema.activities.allStudents, true), byGroup, bySection);
}

export async function canAccessActivity(user: AuthUser, activityId: number): Promise<boolean> {
  if (isAdmin(user)) return true;
  const cond = await activityScope(user);
  const [row] = await db.select({ id: schema.activities.id }).from(schema.activities).where(and(eq(schema.activities.id, activityId), cond)).limit(1);
  return !!row;
}

export async function canManageAttendance(user: AuthUser, activityId: number): Promise<boolean> {
  return (isAdmin(user) || isLeader(user)) && (await canAccessActivity(user, activityId));
}

export const canManageFees = (user: AuthUser) => hasPermission(user, 'canManageFees');
