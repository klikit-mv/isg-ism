import { and, eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { createActivity, listActivities, markableStudentIds, resolveStudentIds, updateActivity, deleteActivity } from '@/server/activities';
import { markAttendance } from '@/server/attendance';
import { updateActivityFee } from '@/server/class-fees';
import { NotAccessibleError, ScoutError } from '@/server/errors';
import { list, unreadCount } from '@/server/notifications';
import { setSetting } from '@/server/settings';
import { participants, markRovers } from '@/server/rover-attendance';
import { makeActivity, makeAdmin, makeGroup, makeLeader, makeParentOf, makeStudent, makeStudentUser } from '../factories';

const feeOf = async (activityId: number, studentId: number) =>
  (await db.select().from(schema.classFees).where(and(eq(schema.classFees.activityId, activityId), eq(schema.classFees.studentId, studentId))))[0];

describe('activity roster', () => {
  it('is the de-duplicated union of active scouts from sections and groups', async () => {
    const a = await makeStudent({ section: 'Scout' });
    const b = await makeStudent({ section: 'Cub Scout' });
    const inactive = await makeStudent({ section: 'Scout', status: 'inactive' });
    const group = await makeGroup({ members: [a, b, inactive] });
    const activity = await makeActivity({ sections: ['Scout'], groups: [group] });
    expect((await resolveStudentIds(activity)).sort()).toEqual([a.id, b.id].sort());
  });

  it('all-scouts targets every active scout', async () => {
    const scouts = [await makeStudent(), await makeStudent({ section: 'Rover' })];
    await makeStudent({ status: 'pending' });
    const activity = await makeActivity({ all: true });
    expect((await resolveStudentIds(activity)).sort()).toEqual(scouts.map((s) => s.id).sort());
  });
});

describe('creating activities', () => {
  it('notifies roster scouts and their parents once, never the creator', async () => {
    const admin = await makeAdmin();
    const scout = await makeStudent();
    const scoutUser = await makeStudentUser(scout);
    const parent = await makeParentOf(scout);
    const activity = await createActivity({ name: 'Camp', date: '2026-05-01', all_students: true, charge_fee: false, sections: [], groups: [] }, admin);
    expect(activity.name).toBe('Camp');
    expect(await unreadCount(scoutUser.id)).toBe(1);
    expect(await unreadCount(parent.id)).toBe(1);
    expect(await unreadCount(admin.id)).toBe(0);
    expect((await list(parent.id))[0].data.title).toBe('New activity: Camp');
  });

  it('a parent who is also on the roster is told once', async () => {
    const admin = await makeAdmin();
    const child = await makeStudent();
    const parentScout = await makeStudent({ section: 'Rover' });
    const both = await makeStudentUser(parentScout);
    await db.insert(schema.parentStudentLinks).values({ parentUserId: both.id, studentId: child.id, status: 'approved' });
    await createActivity({ name: 'Hike', date: '2026-05-01', all_students: true, charge_fee: false }, admin);
    expect(await unreadCount(both.id)).toBe(1);
  });

  it('a template belongs to one activity, and the fee defaults to the settings', async () => {
    const admin = await makeAdmin();
    await setSetting('default_class_fee', '35');
    const [tpl] = await db.insert(schema.certificateTemplates).values({ templateId: 'T1', name: 'General', type: 'general' }).$returningId();
    const first = await createActivity({ name: 'A', date: '2026-05-01', all_students: true, charge_fee: true, certificate_template_id: tpl.id }, admin);
    expect(first.feeAmount).toBe('35.00');
    const second = await createActivity({ name: 'B', date: '2026-05-02', all_students: true, charge_fee: false, certificate_template_id: tpl.id }, admin);
    const [t] = await db.select().from(schema.certificateTemplates);
    expect(t.activityId).toBe(second.id);
    await updateActivity(second, { name: 'B', date: '2026-05-02', all_students: true, charge_fee: false, certificate_template_id: null }, admin);
    expect((await db.select().from(schema.certificateTemplates))[0].activityId).toBeNull();
  });

  it('deleting hides the activity from lists', async () => {
    const admin = await makeAdmin();
    const activity = await makeActivity({ all: true, name: 'Gone' });
    await deleteActivity(activity, admin);
    const page = await listActivities(admin, { page: 1 });
    expect(page.rows.map((r) => r.name)).not.toContain('Gone');
  });

  it('a leader lists only activities in their scope', async () => {
    const leader = await makeLeader();
    const mine = await makeStudent({ section: 'Scout' });
    const group = await makeGroup({ leaders: [leader], members: [mine] });
    await makeActivity({ name: 'For my group', groups: [group] });
    await makeActivity({ name: 'For my section', sections: ['Scout'] });
    await makeActivity({ name: 'Everyone', all: true });
    await makeActivity({ name: 'Not mine', sections: ['Rover'] });
    const names = (await listActivities(leader, { page: 1 })).rows.map((r) => r.name).sort();
    expect(names).toEqual(['Everyone', 'For my group', 'For my section']);
  });
});

describe('marking attendance and class fees', () => {
  it('Present, Late and Absent create a class fee at the activity fee', async () => {
    const admin = await makeAdmin();
    const [a, b, c] = [await makeStudent(), await makeStudent(), await makeStudent()];
    const activity = await makeActivity({ all: true, charge: true, fee: '20.00' });
    await markAttendance(activity, admin, { [a.id]: { status: 'Present' }, [b.id]: { status: 'Late' }, [c.id]: { status: 'Absent' } });
    for (const s of [a, b, c]) {
      const fee = await feeOf(activity.id, s.id);
      expect(fee.amount).toBe('20.00');
      expect(fee.status).toBe('Pending');
      expect(fee.dueDate).toBe('2026-03-15');
    }
  });

  it('falls back to the default class fee, and Excused or uncharged activities create none', async () => {
    const admin = await makeAdmin();
    await setSetting('default_class_fee', '35');
    const s = await makeStudent();
    const noAmount = await makeActivity({ all: true, charge: true, fee: null });
    await markAttendance(noAmount, admin, { [s.id]: { status: 'Present' } });
    expect((await feeOf(noAmount.id, s.id)).amount).toBe('35.00');

    const excused = await makeActivity({ all: true, charge: true });
    await markAttendance(excused, admin, { [s.id]: { status: 'Excused' } });
    expect(await feeOf(excused.id, s.id)).toBeUndefined();

    const free = await makeActivity({ all: true, charge: false });
    await markAttendance(free, admin, { [s.id]: { status: 'Present' } });
    expect(await feeOf(free.id, s.id)).toBeUndefined();
  });

  it('changing to Excused voids an unpaid fee and back re-opens it', async () => {
    const admin = await makeAdmin();
    const s = await makeStudent();
    const activity = await makeActivity({ all: true, charge: true });
    await markAttendance(activity, admin, { [s.id]: { status: 'Present' } });
    await markAttendance(activity, admin, { [s.id]: { status: 'Excused' } });
    let fee = await feeOf(activity.id, s.id);
    expect(fee.status).toBe('Void');
    expect(fee.outstandingAmount).toBe('0.00');
    await markAttendance(activity, admin, { [s.id]: { status: 'Late' } });
    fee = await feeOf(activity.id, s.id);
    expect(fee.status).toBe('Pending');
    expect(fee.outstandingAmount).toBe('20.00');
    expect(fee.voidedAt).toBeNull();
  });

  it('Excused leaves a fee that already has a payment in place', async () => {
    const admin = await makeAdmin();
    const s = await makeStudent();
    const activity = await makeActivity({ all: true, charge: true });
    await markAttendance(activity, admin, { [s.id]: { status: 'Present', payment: '10' } });
    await markAttendance(activity, admin, { [s.id]: { status: 'Excused' } });
    expect((await feeOf(activity.id, s.id)).status).toBe('Partial');
  });

  it('roster payments: presets, custom amount, zero and the cap', async () => {
    const admin = await makeAdmin();
    const [a, b, c] = [await makeStudent(), await makeStudent(), await makeStudent()];
    const activity = await makeActivity({ all: true, charge: true, fee: '20.00' });
    await markAttendance(activity, admin, { [a.id]: { status: 'Present', payment: '10' }, [b.id]: { status: 'Present', payment: '12.5' }, [c.id]: { status: 'Present', payment: '99' } });
    expect((await feeOf(activity.id, a.id)).paidAmount).toBe('10.00');
    expect((await feeOf(activity.id, b.id)).paidAmount).toBe('12.50');
    const capped = await feeOf(activity.id, c.id);
    expect(capped.paidAmount).toBe('20.00');
    expect(capped.status).toBe('Paid');
  });

  it('changing the roster payment to zero rejects it', async () => {
    const admin = await makeAdmin();
    const s = await makeStudent();
    const activity = await makeActivity({ all: true, charge: true });
    await markAttendance(activity, admin, { [s.id]: { status: 'Present', payment: '10' } });
    await markAttendance(activity, admin, { [s.id]: { status: 'Present', payment: '0' } });
    const fee = await feeOf(activity.id, s.id);
    expect(fee.paidAmount).toBe('0.00');
    expect(fee.status).toBe('Pending');
    const [p] = await db.select().from(schema.payments);
    expect(p.status).toBe('Rejected');
  });

  it('roster cash is capped by what other approved payments already covered', async () => {
    const admin = await makeAdmin();
    const s = await makeStudent();
    const activity = await makeActivity({ all: true, charge: true, fee: '20.00' });
    await markAttendance(activity, admin, { [s.id]: { status: 'Present' } });
    const fee = await feeOf(activity.id, s.id);
    const { submitPayment } = await import('@/server/payments');
    const { loadPayableOrThrow } = await import('@/server/payables');
    await submitPayment(await loadPayableOrThrow('class_fee', { id: fee.id }), admin, '15.00', 'cash');
    await markAttendance(activity, admin, { [s.id]: { status: 'Present', payment: '10' } });
    const after = await feeOf(activity.id, s.id);
    expect(after.paidAmount).toBe('20.00');
    const roster = (await db.select().from(schema.payments)).find((p) => p.source === 'roster')!;
    expect(roster.amount).toBe('5.00');
  });

  it('a leader records roster cash without the verify permission', async () => {
    const leader = await makeLeader();
    const s = await makeStudent();
    await makeGroup({ leaders: [leader], members: [s] });
    const activity = await makeActivity({ all: true, charge: true });
    await markAttendance(activity, leader, { [s.id]: { status: 'Present', payment: '10' } });
    expect((await feeOf(activity.id, s.id)).paidAmount).toBe('10.00');
  });

  it('marking a scout outside the scope fails the whole save', async () => {
    const leader = await makeLeader();
    const mine = await makeStudent();
    const other = await makeStudent();
    await makeGroup({ leaders: [leader], members: [mine] });
    const activity = await makeActivity({ all: true, charge: true });
    await expect(markAttendance(activity, leader, { [mine.id]: { status: 'Present' }, [other.id]: { status: 'Present' } })).rejects.toThrow(NotAccessibleError);
    expect(await db.select().from(schema.attendanceRecords)).toHaveLength(0);
    expect(await markableStudentIds(activity, leader)).toEqual([mine.id]);
  });

  it('a leader without groups cannot manage attendance', async () => {
    const leader = await makeLeader();
    const s = await makeStudent();
    const activity = await makeActivity({ all: true });
    await expect(markAttendance(activity, leader, { [s.id]: { status: 'Present' } })).rejects.toThrow('You cannot manage attendance for this activity.');
  });

  it('blank rows are skipped', async () => {
    const admin = await makeAdmin();
    const [a, b] = [await makeStudent(), await makeStudent()];
    const activity = await makeActivity({ all: true });
    const result = await markAttendance(activity, admin, { [a.id]: { status: 'Present' }, [b.id]: { status: '' } });
    expect(result.marked).toBe(1);
  });

  it('re-pricing an activity fee updates every non-void fee', async () => {
    const admin = await makeAdmin();
    const [a, b] = [await makeStudent(), await makeStudent()];
    const activity = await makeActivity({ all: true, charge: true, fee: '20.00' });
    await markAttendance(activity, admin, { [a.id]: { status: 'Present' }, [b.id]: { status: 'Excused' } });
    expect(await updateActivityFee(activity, '30.00', admin)).toBe(1);
    expect((await feeOf(activity.id, a.id)).amount).toBe('30.00');
    expect((await feeOf(activity.id, a.id)).outstandingAmount).toBe('30.00');
    const free = await makeActivity({ all: true, charge: false });
    await expect(updateActivityFee(free, '10', admin)).rejects.toThrow('This activity does not charge a fee.');
  });
});

describe('rover attendance', () => {
  it('splits Rovers into required, optional and available', async () => {
    const [member, assistant, unrelated] = [await makeStudent({ section: 'Rover' }), await makeStudent({ section: 'Rover' }), await makeStudent({ section: 'Rover' })];
    const group = await makeGroup({ members: [member] });
    await db.insert(schema.groupAssistantLeaders).values({ groupId: group.id, studentId: assistant.id });
    const activity = await makeActivity({ groups: [group] });
    const p = await participants(activity);
    expect(p.required.map((s) => s.id)).toEqual([member.id]);
    expect(p.optional.map((s) => s.id)).toEqual([assistant.id]);
    expect(p.available.map((s) => s.id)).toEqual([unrelated.id]);
    expect(p.additional).toEqual([]);
  });

  it('optional Rovers can only be Present; required take Absent; no fee is created', async () => {
    const admin = await makeAdmin();
    const required = await makeStudent({ section: 'Rover' });
    const optional = await makeStudent({ section: 'Rover' });
    const group = await makeGroup();
    await db.insert(schema.groupAssistantLeaders).values({ groupId: group.id, studentId: optional.id });
    const activity = await makeActivity({ groups: [group], sections: [], charge: true });
    await db.insert(schema.groupMembers).values({ groupId: group.id, studentId: required.id });
    await expect(markRovers(activity, admin, { [optional.id]: 'Absent' })).rejects.toThrow('Optional Rovers can only be marked Present.');
    await markRovers(activity, admin, { [required.id]: 'Absent', [optional.id]: 'Present' });
    expect(await db.select().from(schema.classFees)).toHaveLength(0);
    expect((await db.select().from(schema.roverAttendanceRecords)).map((r) => r.status).sort()).toEqual(['Absent', 'Present']);
  });

  it('rejects people who are not active Rovers', async () => {
    const admin = await makeAdmin();
    const scout = await makeStudent({ section: 'Scout' });
    const activity = await makeActivity({ all: true });
    await expect(markRovers(activity, admin, { [scout.id]: 'Present' })).rejects.toThrow('One of the selected people is not an active Rover. Nothing was saved.');
    void ScoutError;
  });
});
