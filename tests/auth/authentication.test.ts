import { createHash } from 'node:crypto';
import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { authenticate, changePin, createSession, destroySession, userForSession } from '@/server/auth';
import { ValidationError } from '@/server/errors';
import { hashPin, loadUser } from '@/server/users';
import { makeAdmin, makeUser, PIN } from '../factories';

const fails = async (p: Promise<unknown>, field: string, message?: string) => {
  const error = await p.then(() => null, (e) => e);
  expect(error).toBeInstanceOf(ValidationError);
  if (message) expect((error as ValidationError).fields[field]).toContain(message);
};

describe('sign in', () => {
  it('signs an active user in with National ID and PIN, case-insensitively', async () => {
    const user = await makeAdmin({ nationalId: 'A123456' });
    const signedIn = await authenticate(' a123456 ', PIN, '1.1.1.1');
    expect(signedIn.id).toBe(user.id);
    expect((await loadUser(user.id))!.lastLoginAt).not.toBeNull();
    const [log] = await db.select().from(schema.auditLogs).where(eq(schema.auditLogs.action, 'auth.login'));
    expect(log.actorUserId).toBe(user.id);
  });

  it('refuses a wrong PIN', async () => {
    await makeUser({ nationalId: 'A123456' });
    await fails(authenticate('A123456', '9999', '1.1.1.1'), 'national_id', 'These details do not match an active account.');
  });

  it('tells a pending registration to wait for verification', async () => {
    await makeUser({ nationalId: 'A123456', status: 'inactive', verified: false });
    await fails(authenticate('A123456', PIN, '1.1.1.1'), 'national_id', 'Your registration is waiting for a leader to verify it.');
  });

  it('gives a generic message for an inactive, verified account', async () => {
    await makeUser({ nationalId: 'A123456', status: 'inactive' });
    await fails(authenticate('A123456', PIN, '1.1.1.1'), 'national_id', 'These details do not match an active account.');
  });

  it.each([
    ['salt+pin', (s: string, p: string) => s + p],
    ['pin+salt', (s: string, p: string) => p + s],
    ['pin only', (_s: string, p: string) => p],
  ])('accepts a legacy SHA-256 PIN once and rehashes it (%s)', async (_name, build) => {
    const user = await makeUser({ nationalId: 'A123456' });
    await db.update(schema.users).set({
      password: await hashPin('some-random-secret'),
      legacyPinSalt: 'NaCl',
      legacyPinHash: createHash('sha256').update(build('NaCl', '4321')).digest('hex'),
    }).where(eq(schema.users.id, user.id));

    await authenticate('A123456', '4321', '1.1.1.1');

    const after = (await loadUser(user.id))!;
    expect(after.legacyPinHash).toBeNull();
    expect(after.legacyPinSalt).toBeNull();
    await authenticate('A123456', '4321', '1.1.1.1');
    const actions = (await db.select().from(schema.auditLogs)).map((l) => l.action);
    expect(actions).toContain('auth.pin_rehashed');
  });

  it('is throttled after five failures', async () => {
    await makeUser({ nationalId: 'A123456' });
    for (let i = 0; i < 5; i++) await authenticate('A123456', '0000', '1.1.1.1').catch(() => null);
    await fails(authenticate('A123456', PIN, '1.1.1.1'), 'national_id', 'Too many sign-in attempts');
    // A different address is not locked out.
    await authenticate('A123456', PIN, '2.2.2.2');
  });

  it('never writes PINs to the audit log', async () => {
    await makeAdmin({ nationalId: 'A123456' });
    await authenticate('A123456', PIN, '1.1.1.1');
    for (const log of await db.select().from(schema.auditLogs)) expect(JSON.stringify(log.details)).not.toContain(PIN);
  });
});

describe('sessions', () => {
  it('creates, resolves and destroys a session; inactive users lose access', async () => {
    const user = await makeUser();
    const { token } = await createSession(user.id, '1.1.1.1', 'test');
    expect((await userForSession(token))?.id).toBe(user.id);
    expect(await userForSession('wrong')).toBeNull();

    await db.update(schema.users).set({ status: 'inactive' }).where(eq(schema.users.id, user.id));
    expect(await userForSession(token)).toBeNull();
    await db.update(schema.users).set({ status: 'active' }).where(eq(schema.users.id, user.id));

    await destroySession(token);
    expect(await userForSession(token)).toBeNull();
  });

  it('ignores expired sessions', async () => {
    const user = await makeUser();
    const { token } = await createSession(user.id, null, null);
    await db.update(schema.appSessions).set({ expiresAt: new Date(Date.now() - 1000) });
    expect(await userForSession(token)).toBeNull();
  });
});

describe('changing the PIN', () => {
  it('changes it with the current PIN', async () => {
    const user = (await makeUser())!;
    await changePin(user, PIN, '5678', '5678');
    await authenticate(user.nationalId, '5678', '3.3.3.3');
  });

  it('refuses a wrong current PIN', async () => {
    const user = await makeUser();
    await fails(changePin(user, '0000', '5678', '5678'), 'current_pin');
    await authenticate(user.nationalId, PIN, '3.3.3.3');
  });

  it('needs 4 to 32 characters, confirmed', async () => {
    const user = await makeUser();
    await fails(changePin(user, PIN, '12', '12'), 'pin');
    await fails(changePin(user, PIN, '5678', '8765'), 'pin');
  });
});
