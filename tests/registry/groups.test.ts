import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { createGroup, deleteGroup, groupMembership, renameGroup, syncMembership } from '@/server/groups';
import { canAccessGroup, leaderGroupIds } from '@/server/scope';
import { makeAdmin, makeGroup, makeLeader, makeStudent, makeUser } from '../factories';

describe('groups', () => {
  it('the creator becomes owner and first leader', async () => {
    const admin = await makeAdmin();
    const group = await createGroup('Eagle Patrol', 'Patrol', admin);
    expect(group.ownerId).toBe(admin.id);
    expect((await groupMembership(group.id)).leaders).toEqual([admin.id]);
  });

  it('a leader edits membership of their own group only', async () => {
    const leader = await makeLeader();
    const own = await makeGroup({ leaders: [leader] });
    const other = await makeGroup();
    expect(await canAccessGroup(leader, own.id)).toBe(true);
    expect(await canAccessGroup(leader, other.id)).toBe(false);
    const scout = await makeStudent();
    await syncMembership(own, [scout.id], [leader.id], [], leader);
    expect((await groupMembership(own.id)).members).toEqual([scout.id]);
  });

  it('only leader-role users can lead', async () => {
    const admin = await makeAdmin();
    const group = await makeGroup();
    const parent = await makeUser({ roles: ['parent'] });
    await expect(syncMembership(group, [], [parent.id], [], admin)).rejects.toThrow('Only users with the leader role can be assigned as group leaders.');
  });

  it('assistant leaders must be Rovers', async () => {
    const admin = await makeAdmin();
    const group = await makeGroup();
    const scout = await makeStudent();
    const rover = await makeStudent({ section: 'Rover' });
    await expect(syncMembership(group, [], [], [scout.id], admin)).rejects.toThrow('Assistant leaders must be Rover scouts.');
    await syncMembership(group, [], [], [rover.id], admin);
    expect((await groupMembership(group.id)).assistants).toEqual([rover.id]);
  });

  it('a section group only accepts scouts of that section', async () => {
    const admin = await makeAdmin();
    const group = await createGroup('Cub Pack', null, admin, 'Cub Scout');
    const cub = await makeStudent({ section: 'Cub Scout' });
    const scout = await makeStudent({ section: 'Scout', name: 'Wrong Section' });
    await expect(syncMembership(group, [scout.id], [admin.id], [], admin)).rejects.toThrow('Wrong Section is not in the Cub Scout section, so cannot join this group.');
    await syncMembership(group, [cub.id], [admin.id], [], admin);
    expect((await groupMembership(group.id)).members).toEqual([cub.id]);
  });

  it('renaming and deleting are audited; deleted groups leave the leader scope', async () => {
    const admin = await makeAdmin();
    const leader = await makeLeader();
    const group = await makeGroup({ leaders: [leader], name: 'Old' });
    await renameGroup(group, 'New', 'Six', admin, 'Scout');
    expect((await db.select().from(schema.groups).where(eq(schema.groups.id, group.id)))[0].name).toBe('New');
    await deleteGroup(group, admin);
    expect(await leaderGroupIds(leader)).toEqual([]);
    const actions = (await db.select().from(schema.auditLogs)).map((l) => l.action);
    expect(actions).toEqual(expect.arrayContaining(['group.renamed', 'group.deleted']));
  });
});
