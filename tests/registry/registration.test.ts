import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { authenticate } from '@/server/auth';
import { ScoutError, ValidationError } from '@/server/errors';
import { registerParent } from '@/server/parents';
import { registerStudent, rejectRegistration, verifyRegistration } from '@/server/students';
import { rejectParentRegistration, verifyParentRegistration } from '@/server/user-admin';
import { list, unreadCount } from '@/server/notifications';
import { loadUser } from '@/server/users';
import { makeAdmin, makeLeader, makeParentOf, makeStudent, makeUser } from '../factories';

const scout = (over: Record<string, unknown> = {}) => ({
  index_number: 'IX12345', national_id: 'a1234567', name: 'New Scout', email: 'new@example.com', gender: 'Female',
  permanent_address: 'Addu', present_address: 'Male', date_of_birth: '2014-03-15', parent_name: 'Parent', primary_mobile: '7771234',
  section: 'Cub Scout', pin: '4321', pin_confirmation: '4321', ...over,
});

describe('scout registration', () => {
  it('creates a pending scout with an inactive account and notifies staff', async () => {
    const leader = await makeLeader();
    const student = await registerStudent(scout());

    expect(student.status).toBe('pending');
    expect(student.nationalId).toBe('A1234567');
    const account = (await db.select().from(schema.users).where(eq(schema.users.studentId, student.id)))[0];
    expect(account.status).toBe('inactive');
    expect((await loadUser(account.id))!.roles).toEqual(['student']);
    expect(await unreadCount(leader.id)).toBe(1);
    expect((await list(leader.id))[0].data.title).toBe('New scout registration');
  });

  it('rejects a National ID already used by any user', async () => {
    await makeUser({ nationalId: 'A1234567' });
    const error = await registerStudent(scout()).catch((e) => e);
    expect(error).toBeInstanceOf(ValidationError);
    expect(error.fields.national_id).toContain('already been taken');
  });

  it('needs a date of birth in the past and a confirmed PIN', async () => {
    const future = await registerStudent(scout({ date_of_birth: '2999-01-01' })).catch((e) => e);
    expect(future.fields.date_of_birth).toBeDefined();
    const pin = await registerStudent(scout({ pin_confirmation: '0000' })).catch((e) => e);
    expect(pin.fields.pin).toBeDefined();
  });

  it('cannot sign in until a leader verifies it', async () => {
    const student = await registerStudent(scout());
    const message = await authenticate('A1234567', '4321', '9.9.9.9').catch((e) => e.fields.national_id);
    expect(message).toBe('Your registration is waiting for a leader to verify it.');

    const leader = await makeLeader();
    await verifyRegistration(student.id, leader.id);
    const user = await authenticate('A1234567', '4321', '9.9.9.9');
    expect(user.status).toBe('active');
    const [after] = await db.select().from(schema.students).where(eq(schema.students.id, student.id));
    expect(after.status).toBe('active');
    expect(after.verifiedBy).toBe(leader.id);
    expect(await unreadCount(user.id)).toBe(1);
  });

  it('a declined registration stays inactive', async () => {
    const student = await registerStudent(scout());
    await rejectRegistration(student.id, await makeAdmin());
    const [after] = await db.select().from(schema.students).where(eq(schema.students.id, student.id));
    expect(after.status).toBe('inactive');
    const message = await authenticate('A1234567', '4321', '9.9.9.9').catch((e) => e.fields.national_id);
    expect(message).toBe('These details do not match an active account.');
  });
});

describe('parent registration', () => {
  const input = (children: string[], over: Record<string, unknown> = {}) => ({
    name: 'New Parent', national_id: 'p111111', email: 'parent@example.com', pin: '4321', pin_confirmation: '4321', children, ...over,
  });

  it('creates an inactive parent with pending links', async () => {
    const child = await makeStudent();
    const leader = await makeLeader();
    const parent = await registerParent(input([child.nationalId]));

    const [links] = await Promise.all([db.select().from(schema.parentStudentLinks).where(eq(schema.parentStudentLinks.parentUserId, parent.id))]);
    expect(links).toHaveLength(1);
    expect(links[0].status).toBe('pending');
    const user = (await loadUser(parent.id))!;
    expect(user.status).toBe('inactive');
    expect(user.roles).toEqual(['parent']);
    expect(await unreadCount(leader.id)).toBe(1);
  });

  it('refuses a scout who already has a parent', async () => {
    const child = await makeStudent();
    await makeParentOf(child);
    await expect(registerParent(input([child.nationalId]))).rejects.toThrow('This scout is already added under another parent.');
    expect(await db.select().from(schema.users).where(eq(schema.users.nationalId, 'P111111'))).toHaveLength(0);
  });

  it('refuses an unknown National ID and needs at least one child', async () => {
    await expect(registerParent(input(['ZZ999999']))).rejects.toThrow('No scout matches National ID ZZ999999.');
    const error = await registerParent(input([])).catch((e) => e);
    expect(error).toBeInstanceOf(ValidationError);
    expect(error.fields.children).toBe('Add at least one child by National ID.');
  });

  it('verifying approves the links; declining rejects them', async () => {
    const admin = await makeAdmin();
    const [a, b] = [await makeStudent(), await makeStudent()];
    const p1 = await registerParent(input([a.nationalId]));
    await verifyParentRegistration(p1.id, admin);
    expect((await db.select().from(schema.parentStudentLinks).where(eq(schema.parentStudentLinks.parentUserId, p1.id)))[0].status).toBe('approved');
    expect((await loadUser(p1.id))!.status).toBe('active');
    expect(await unreadCount(p1.id)).toBe(1);

    const p2 = await registerParent(input([b.nationalId], { national_id: 'P222222', email: 'two@example.com' }));
    await rejectParentRegistration(p2.id, admin);
    expect((await db.select().from(schema.parentStudentLinks).where(eq(schema.parentStudentLinks.parentUserId, p2.id)))[0].status).toBe('rejected');
    expect((await loadUser(p2.id))!.status).toBe('inactive');
  });
});

it('ScoutError is a plain business error', () => {
  expect(new ScoutError('x')).toBeInstanceOf(Error);
});
