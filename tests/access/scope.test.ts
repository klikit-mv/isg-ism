import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { eq } from 'drizzle-orm';
import { canAccessGroup, canAccessStudent, leaderGroupIds, leaderStudentIds, studentScope } from '@/server/scope';
import { canOpen, findModule, modulesFor, moduleForPath, navFor } from '@/lib/modules';
import { makeAdmin, makeGroup, makeLeader, makeParentOf, makeStudent, makeStudentUser, makeUser } from '../factories';

const idsOf = async (user: Parameters<typeof studentScope>[0]) => {
  const cond = await studentScope(user);
  const rows = await db.select({ id: schema.students.id }).from(schema.students).where(cond);
  return rows.map((r) => r.id).sort();
};

describe('who can see which scouts', () => {
  it('a parent sees only approved children', async () => {
    const [a, b, c] = [await makeStudent(), await makeStudent(), await makeStudent()];
    const parent = await makeParentOf(a);
    await db.insert(schema.parentStudentLinks).values({ parentUserId: parent.id, studentId: b.id, status: 'pending' });
    expect(await leaderStudentIds(parent)).toEqual([a.id]);
    expect(await canAccessStudent(parent, a)).toBe(true);
    expect(await canAccessStudent(parent, b)).toBe(false);
    expect(await canAccessStudent(parent, c)).toBe(false);
  });

  it.each(['pending', 'rejected', 'inactive'])('a %s link grants nothing', async (status) => {
    const child = await makeStudent();
    const parent = await makeParentOf(child);
    await db.update(schema.parentStudentLinks).set({ status }).where(eq(schema.parentStudentLinks.parentUserId, parent.id));
    expect(await leaderStudentIds(parent)).toEqual([]);
  });

  it('a scout sees only their own record', async () => {
    const mine = await makeStudent();
    const other = await makeStudent();
    const user = await makeStudentUser(mine);
    expect(await idsOf(user)).toEqual([mine.id]);
    expect(await canAccessStudent(user, other)).toBe(false);
  });

  it('a leader is bound to the groups they lead, plus every pending registration', async () => {
    const leader = await makeLeader();
    const inGroup = await makeStudent();
    const outside = await makeStudent();
    const pending = await makeStudent({ status: 'pending' });
    const group = await makeGroup({ leaders: [leader], members: [inGroup] });
    await makeGroup({ members: [outside] });

    expect(await leaderGroupIds(leader)).toEqual([group.id]);
    expect(await idsOf(leader)).toEqual([inGroup.id, pending.id].sort());
    expect(await canAccessStudent(leader, outside)).toBe(false);
    expect(await canAccessStudent(leader, pending)).toBe(true);
    expect(await canAccessGroup(leader, group.id)).toBe(true);
  });

  it('an admin is unrestricted', async () => {
    const admin = await makeAdmin();
    const s = await makeStudent();
    expect(await leaderStudentIds(admin)).toBeNull();
    expect(await studentScope(admin)).toBeUndefined();
    expect(await canAccessStudent(admin, s)).toBe(true);
  });

  it('a person with several roles keeps independent abilities', async () => {
    const child = await makeStudent();
    const both = await makeUser({ roles: ['leader', 'parent'] });
    await db.insert(schema.parentStudentLinks).values({ parentUserId: both.id, studentId: child.id, status: 'approved' });
    const loaded = (await import('@/server/users')).loadUser;
    expect(await leaderStudentIds((await loaded(both.id))!)).toEqual([child.id]);
    expect(await leaderGroupIds(both)).toEqual([]);
  });
});

describe('modules', () => {
  it('the hub shows only modules the user can open', async () => {
    const parent = await makeParentOf(await makeStudent());
    expect(modulesFor(parent).map((m) => m.key)).toEqual(['family', 'certificates', 'finance', 'shop', 'events']);
    const admin = await makeAdmin();
    expect(modulesFor(admin).map((m) => m.key)).toContain('administration');
    const leader = await makeLeader();
    expect(modulesFor(leader).map((m) => m.key)).not.toContain('administration');
  });

  it('a parent who is also a scout gets both modules separately', async () => {
    const both = await makeUser({ roles: ['parent', 'student'] });
    const keys = modulesFor(both).map((m) => m.key);
    expect(keys).toContain('family');
    expect(keys).toContain('self');
  });

  it('finds the module for a path, longest prefix first', () => {
    expect(moduleForPath('/students/abc/edit')?.key).toBe('operations');
    expect(moduleForPath('/events/registrations')?.key).toBe('events');
    expect(moduleForPath('/certificates/verify')?.key).toBe('certificates');
    expect(moduleForPath('/profile')).toBeNull();
  });

  it('the sidebar honours roles and permissions', async () => {
    const leader = await makeLeader();
    const finance = findModule('finance')!;
    expect(navFor(leader, finance).map((l) => l.label)).not.toContain('Verification');
    const treasurer = await makeUser({ roles: ['leader'], permissions: ['canVerifyPayments'] });
    expect(navFor(treasurer, finance).map((l) => l.label)).toContain('Verification');
    expect(canOpen(await makeStudentUser(), findModule('operations')!)).toBe(false);
  });
});
