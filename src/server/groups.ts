import { and, asc, count, eq, inArray, isNull, like } from 'drizzle-orm';
import { db, schema } from '@/db';
import type { ScoutSectionValue } from '@/lib/enums';
import { PAGE_SIZE, pageOf } from '@/components/Pagination';
import { recordAudit } from './audit';
import { ScoutError } from './errors';
import { leaderGroupIds } from './scope';
import { isAdmin, loadUsers, type AuthUser } from './users';

export type GroupRow = typeof schema.groups.$inferSelect;

export const findGroupByUuid = async (uuid: string): Promise<GroupRow | null> => {
  const [row] = await db.select().from(schema.groups).where(and(eq(schema.groups.uuid, uuid), isNull(schema.groups.deletedAt))).limit(1);
  return row ?? null;
};

export async function createGroup(name: string, type: string | null, actor: AuthUser, section: ScoutSectionValue | null = null): Promise<GroupRow> {
  const now = new Date();
  return db.transaction(async (tx) => {
    const [ins] = await tx.insert(schema.groups).values({ name, type, section, ownerId: actor.id, status: 'Active', createdAt: now, updatedAt: now }).$returningId();
    await tx.insert(schema.groupLeaders).values({ groupId: ins.id, userId: actor.id, createdAt: now, updatedAt: now });
    await recordAudit('group.created', { type: 'group', id: ins.id }, { name, type, section }, actor.id, tx);
    const [group] = await tx.select().from(schema.groups).where(eq(schema.groups.id, ins.id));
    return group;
  });
}

export async function renameGroup(group: GroupRow, name: string, type: string | null, actor: AuthUser, section: ScoutSectionValue | null = null): Promise<void> {
  await db.update(schema.groups).set({ name, type, section, updatedAt: new Date() }).where(eq(schema.groups.id, group.id));
  await recordAudit('group.renamed', { type: 'group', id: group.id }, { from: group.name, to: name }, actor.id);
}

export async function deleteGroup(group: GroupRow, actor: AuthUser): Promise<void> {
  await recordAudit('group.deleted', { type: 'group', id: group.id }, { name: group.name }, actor.id);
  await db.update(schema.groups).set({ deletedAt: new Date() }).where(eq(schema.groups.id, group.id));
}

/** Replace members, leaders and Rover assistant leaders in one transaction. */
export async function syncMembership(group: GroupRow, memberIds: number[], leaderIds: number[], assistantIds: number[], actor: AuthUser): Promise<void> {
  const uniq = (ids: number[]) => [...new Set(ids.map(Number))];
  [memberIds, leaderIds, assistantIds] = [uniq(memberIds), uniq(leaderIds), uniq(assistantIds)];

  const leaders = await loadUsers(leaderIds);
  if (leaders.length !== leaderIds.length || leaders.some((u) => !u.roles.includes('leader') && !u.roles.includes('admin'))) {
    throw new ScoutError('Only users with the leader role can be assigned as group leaders.');
  }
  const rovers = assistantIds.length ? await db.select().from(schema.students).where(inArray(schema.students.id, assistantIds)) : [];
  if (rovers.length !== assistantIds.length || rovers.some((s) => s.section !== 'Rover')) throw new ScoutError('Assistant leaders must be Rover scouts.');

  const members = memberIds.length ? await db.select().from(schema.students).where(inArray(schema.students.id, memberIds)) : [];
  if (members.length !== memberIds.length) throw new ScoutError('One of the selected members no longer exists.');
  if (group.section) {
    const outside = members.find((s) => s.section !== group.section);
    if (outside) throw new ScoutError(`${outside.name} is not in the ${group.section} section, so cannot join this group.`);
  }

  const now = new Date();
  await db.transaction(async (tx) => {
    const sync = async (table: typeof schema.groupMembers | typeof schema.groupLeaders | typeof schema.groupAssistantLeaders, column: 'studentId' | 'userId', ids: number[]) => {
      const t = table as any;
      await tx.delete(t).where(eq(t.groupId, group.id));
      for (const id of ids) await tx.insert(t).values({ groupId: group.id, [column]: id, createdAt: now, updatedAt: now });
    };
    await sync(schema.groupMembers, 'studentId', memberIds);
    await sync(schema.groupLeaders, 'userId', leaderIds);
    await sync(schema.groupAssistantLeaders, 'studentId', assistantIds);
    await recordAudit('group.membership_synced', { type: 'group', id: group.id }, { members: memberIds.length, leaders: leaderIds.length, assistant_leaders: assistantIds.length }, actor.id, tx);
  });
}

export async function listGroups(user: AuthUser, q: string | undefined, page: number) {
  const G = schema.groups;
  const ids = isAdmin(user) ? null : await leaderGroupIds(user);
  const where = and(isNull(G.deletedAt), ids === null ? undefined : inArray(G.id, ids.length ? ids : [0]), q ? like(G.name, `%${q}%`) : undefined);
  const [{ n }] = await db.select({ n: count() }).from(G).where(where);
  const rows = await db.select().from(G).where(where).orderBy(asc(G.name)).limit(PAGE_SIZE).offset((page - 1) * PAGE_SIZE);
  const groupIds = rows.map((r) => r.id);
  const tally = async (table: typeof schema.groupMembers | typeof schema.groupLeaders | typeof schema.groupAssistantLeaders) => {
    const t = table as any;
    if (!groupIds.length) return new Map<number, number>();
    const res = await db.select({ id: t.groupId, n: count() }).from(t).where(inArray(t.groupId, groupIds)).groupBy(t.groupId);
    return new Map<number, number>(res.map((r: any) => [r.id, Number(r.n)]));
  };
  const [members, leaders, assistants] = await Promise.all([tally(schema.groupMembers), tally(schema.groupLeaders), tally(schema.groupAssistantLeaders)]);
  return pageOf(rows.map((g) => ({ ...g, members: members.get(g.id) ?? 0, leaders: leaders.get(g.id) ?? 0, assistants: assistants.get(g.id) ?? 0 })), Number(n), page);
}

export async function groupMembership(groupId: number) {
  const [members, leaders, assistants] = await Promise.all([
    db.select({ id: schema.groupMembers.studentId }).from(schema.groupMembers).where(eq(schema.groupMembers.groupId, groupId)),
    db.select({ id: schema.groupLeaders.userId }).from(schema.groupLeaders).where(eq(schema.groupLeaders.groupId, groupId)),
    db.select({ id: schema.groupAssistantLeaders.studentId }).from(schema.groupAssistantLeaders).where(eq(schema.groupAssistantLeaders.groupId, groupId)),
  ]);
  return { members: members.map((r) => r.id), leaders: leaders.map((r) => r.id), assistants: assistants.map((r) => r.id) };
}
