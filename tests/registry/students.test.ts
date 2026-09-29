import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { authenticate } from '@/server/auth';
import { ScoutError } from '@/server/errors';
import { bulkPromote, createStudent, deleteStudent, setStudentStatus, updateStudent, temporaryPin } from '@/server/students';
import { loadUser } from '@/server/users';
import { makeAdmin, makeStudent } from '../factories';

const data = (over: Record<string, unknown> = {}) => ({
  index_number: 'IX777', national_id: 'a7777777', name: 'Enrolled Scout', email: 'enrolled@example.com', gender: 'Male',
  permanent_address: 'Addu', present_address: 'Addu', date_of_birth: '2013-01-02', parent_name: 'Parent', primary_mobile: '7771111', section: 'Scout',
  class_name: null, patrol: null, status: 'active', ...over,
});

describe('enrolling', () => {
  it('creates an active scout and a sign-in account with a temporary PIN', async () => {
    const admin = await makeAdmin();
    const { student, pin } = await createStudent(data(), admin);
    expect(pin).toMatch(/^\d{4}$/);
    expect(student.nationalId).toBe('A7777777');
    expect(student.verifiedBy).toBe(admin.id);
    const user = await authenticate('A7777777', pin, '1.1.1.1');
    expect(user.studentId).toBe(student.id);
    expect(user.roles).toEqual(['student']);
  });

  it('uses the PIN it is given and leaves a pending scout inactive', async () => {
    const admin = await makeAdmin();
    const { student, pin } = await createStudent(data({ status: 'pending', pin: '5555' }), admin);
    expect(pin).toBe('5555');
    expect(student.verifiedAt).toBeNull();
    await expect(authenticate('A7777777', '5555', '1.1.1.1')).rejects.toBeTruthy();
  });

  it('temporary PINs are four digits', () => {
    for (let i = 0; i < 50; i++) expect(temporaryPin()).toMatch(/^\d{4}$/);
  });
});

describe('changing a scout', () => {
  it('copies name, National ID, email and status to the account', async () => {
    const admin = await makeAdmin();
    const { student } = await createStudent(data(), admin);
    await updateStudent(student.id, data({ name: 'Renamed', national_id: 'a8888888', email: 'r@example.com', status: 'inactive' }), admin);
    const [account] = await db.select().from(schema.users).where(eq(schema.users.studentId, student.id));
    expect(account.name).toBe('Renamed');
    expect(account.nationalId).toBe('A8888888');
    expect(account.email).toBe('r@example.com');
    expect(account.status).toBe('inactive');
    const [log] = await db.select().from(schema.auditLogs).where(eq(schema.auditLogs.action, 'student.updated'));
    expect(JSON.stringify(log.details)).toContain('name');
  });

  it('status changes mirror to the account', async () => {
    const admin = await makeAdmin();
    const { student } = await createStudent(data(), admin);
    await setStudentStatus(student.id, 'inactive', admin);
    expect((await loadUser((await db.select().from(schema.users).where(eq(schema.users.studentId, student.id)))[0].id))!.status).toBe('inactive');
  });

  it('deleting hides the scout and the account but keeps history', async () => {
    const admin = await makeAdmin();
    const { student } = await createStudent(data(), admin);
    await deleteStudent(student.id, admin);
    const [row] = await db.select().from(schema.students).where(eq(schema.students.id, student.id));
    expect(row.deletedAt).not.toBeNull();
    await expect(authenticate('A7777777', '1234', '1.1.1.1')).rejects.toBeTruthy();
    expect((await db.select().from(schema.auditLogs).where(eq(schema.auditLogs.action, 'student.deleted'))).length).toBe(1);
  });
});

describe('promotion', () => {
  it('moves scouts exactly one section forward and skips the rest', async () => {
    const admin = await makeAdmin();
    const [a, b] = [await makeStudent({ section: 'Cub Scout' }), await makeStudent({ section: 'Cub Scout' })];
    const wrong = await makeStudent({ section: 'Scout' });
    const result = await bulkPromote([a.id, b.id, wrong.id, 99999], 'Cub Scout', 'Scout', admin);
    expect(result).toEqual({ promoted: 2, skipped: 2 });
    const sections = (await db.select().from(schema.students)).map((s) => s.section);
    expect(sections.filter((s) => s === 'Scout')).toHaveLength(3);
  });

  it('refuses to skip a section', async () => {
    const admin = await makeAdmin();
    await expect(bulkPromote([1], 'Pre Cub', 'Scout', admin)).rejects.toThrow(ScoutError);
    await expect(bulkPromote([1], 'Pre Cub', 'Scout', admin)).rejects.toThrow('Scouts can only move one section forward, from Pre Cub to Cub Scout.');
  });
});
