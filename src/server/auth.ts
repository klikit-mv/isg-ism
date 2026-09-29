import { createHash, randomBytes, timingSafeEqual } from 'node:crypto';
import { and, eq, gt, lt } from 'drizzle-orm';
import { db, schema } from '@/db';
import { config } from '@/lib/config';
import { recordAudit } from './audit';
import { ScoutError, ValidationError } from './errors';
import * as limiter from './ratelimit';
import { hashPin, loadUser, loadUserByNationalId, verifyPin, type AuthUser } from './users';

export const normalizeNationalId = (value: unknown) => String(value ?? '').trim().toUpperCase();

const isBcrypt = (v: string | null | undefined) => typeof v === 'string' && v.startsWith('$2');

function legacyMatches(hash: string, salt: string, pin: string): boolean {
  const target = Buffer.from(hash.toLowerCase());
  return [salt + pin, pin + salt, pin].some((candidate) => {
    const digest = Buffer.from(createHash('sha256').update(candidate).digest('hex'));
    return digest.length === target.length && timingSafeEqual(digest, target);
  });
}

async function pinMatches(user: AuthUser, pin: string): Promise<boolean> {
  if (user.legacyPinHash === null && isBcrypt(user.password) && (await verifyPin(pin, user.password))) return true;

  if (user.legacyPinHash !== null && legacyMatches(user.legacyPinHash, user.legacyPinSalt ?? '', pin)) {
    await db.update(schema.users)
      .set({ password: await hashPin(pin), legacyPinHash: null, legacyPinSalt: null, updatedAt: new Date() })
      .where(eq(schema.users.id, user.id));
    await recordAudit('auth.pin_rehashed', { type: 'user', id: user.id }, {}, user.id);
    return true;
  }

  return isBcrypt(user.password) && (await verifyPin(pin, user.password));
}

/**
 * Check National ID + PIN (a legacy SHA-256 PIN is accepted once and rehashed).
 * Five failures lock that ID + address for a minute.
 */
export async function authenticate(nationalIdInput: string, pin: string, ip: string): Promise<AuthUser> {
  const nationalId = normalizeNationalId(nationalIdInput);
  if (!nationalId || !pin) throw new ValidationError({ national_id: 'Enter your National ID and PIN.' });

  const key = `login|${nationalId.toLowerCase()}|${ip}`;
  if (await limiter.tooManyAttempts(key, 5)) {
    const seconds = await limiter.availableIn(key);
    throw new ValidationError({ national_id: `Too many sign-in attempts. Please try again in ${seconds} seconds.` });
  }

  const user = await loadUserByNationalId(nationalId);
  if (!user || !(await pinMatches(user, pin))) {
    await limiter.hit(key);
    throw new ValidationError({ national_id: 'These details do not match an active account.' });
  }

  if (user.status !== 'active') {
    await limiter.hit(key);
    throw new ValidationError({
      national_id: user.verifiedAt === null
        ? 'Your registration is waiting for a leader to verify it.'
        : 'These details do not match an active account.',
    });
  }

  await limiter.clear(key);
  await db.update(schema.users).set({ lastLoginAt: new Date() }).where(eq(schema.users.id, user.id));
  await recordAudit('auth.login', { type: 'user', id: user.id }, { ip }, user.id);
  return user;
}

const sessionId = (token: string) => createHash('sha256').update(token).digest('hex');

/** Create a session and return the cookie token. */
export async function createSession(userId: number, ip: string | null, userAgent: string | null): Promise<{ token: string; expiresAt: Date }> {
  const token = randomBytes(32).toString('hex');
  const expiresAt = new Date(Date.now() + config.sessionDays * 86_400_000);
  await db.insert(schema.appSessions).values({
    id: sessionId(token), userId, ipAddress: ip, userAgent: userAgent?.slice(0, 255) ?? null, expiresAt, createdAt: new Date(),
  });
  // Tidy expired sessions now and then.
  if (Math.random() < 0.02) await db.delete(schema.appSessions).where(lt(schema.appSessions.expiresAt, new Date()));
  return { token, expiresAt };
}

export async function userForSession(token: string | undefined | null): Promise<AuthUser | null> {
  if (!token) return null;
  const [session] = await db.select().from(schema.appSessions)
    .where(and(eq(schema.appSessions.id, sessionId(token)), gt(schema.appSessions.expiresAt, new Date()))).limit(1);
  if (!session) return null;
  const user = await loadUser(session.userId);
  return user && user.status === 'active' ? user : null;
}

export async function destroySession(token: string | undefined | null): Promise<void> {
  if (token) await db.delete(schema.appSessions).where(eq(schema.appSessions.id, sessionId(token)));
}

/** Sign out everywhere except (optionally) the current session. */
export async function destroyOtherSessions(userId: number, keepToken?: string | null): Promise<void> {
  const keep = keepToken ? sessionId(keepToken) : null;
  const rows = await db.select({ id: schema.appSessions.id }).from(schema.appSessions).where(eq(schema.appSessions.userId, userId));
  for (const row of rows) {
    if (row.id !== keep) await db.delete(schema.appSessions).where(eq(schema.appSessions.id, row.id));
  }
}

export async function changePin(user: AuthUser, current: string, next: string, confirmation: string): Promise<void> {
  const errors: Record<string, string> = {};
  if (!(await pinMatches(user, current))) errors.current_pin = 'The current PIN is not correct.';
  if (next.length < 4 || next.length > 32) errors.pin = 'The PIN must be between 4 and 32 characters.';
  else if (next !== confirmation) errors.pin = 'The PIN confirmation does not match.';
  if (Object.keys(errors).length) throw new ValidationError(errors);

  await db.update(schema.users)
    .set({ password: await hashPin(next), legacyPinHash: null, legacyPinSalt: null, updatedAt: new Date() })
    .where(eq(schema.users.id, user.id));
  await recordAudit('auth.pin_changed', { type: 'user', id: user.id }, {}, user.id);
}

export { ScoutError };
