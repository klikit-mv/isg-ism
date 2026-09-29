import { cache } from 'react';
import { cookies, headers } from 'next/headers';
import { forbidden, redirect } from 'next/navigation';
import { config } from '@/lib/config';
import { canOpen, findModule } from '@/lib/modules';
import { createSession, destroySession, userForSession } from './auth';
import { isAdmin, isStaff, type AuthUser } from './users';
import type { RoleValue } from '@/lib/enums';

/** The signed-in user for this request, or null. */
export const getUser = cache(async (): Promise<AuthUser | null> => {
  const jar = await cookies();
  return userForSession(jar.get(config.sessionCookie)?.value);
});

export async function requireUser(): Promise<AuthUser> {
  const user = await getUser();
  if (!user) redirect('/login');
  return user;
}

/** Signed in and allowed into the module (403 otherwise). */
export async function requireModule(key: string): Promise<AuthUser> {
  const user = await requireUser();
  const module = findModule(key);
  if (!module || !canOpen(user, module)) forbidden();
  return user;
}

export async function requireRole(...roles: RoleValue[]): Promise<AuthUser> {
  const user = await requireUser();
  if (!user.status || user.status !== 'active' || !roles.some((r) => user.roles.includes(r))) forbidden();
  return user;
}

export async function requireAdmin(): Promise<AuthUser> {
  const user = await requireUser();
  if (!isAdmin(user)) forbidden();
  return user;
}

export async function requireStaff(): Promise<AuthUser> {
  const user = await requireUser();
  if (!isStaff(user)) forbidden();
  return user;
}

export async function clientIp(): Promise<string> {
  const h = await headers();
  return (h.get('x-forwarded-for')?.split(',')[0] ?? h.get('x-real-ip') ?? '0.0.0.0').trim();
}

export async function startSession(userId: number): Promise<void> {
  const h = await headers();
  const { token, expiresAt } = await createSession(userId, await clientIp(), h.get('user-agent'));
  (await cookies()).set(config.sessionCookie, token, {
    httpOnly: true, sameSite: 'lax', secure: config.isProduction, path: '/', expires: expiresAt,
  });
}

export async function endSession(): Promise<void> {
  const jar = await cookies();
  await destroySession(jar.get(config.sessionCookie)?.value);
  jar.delete(config.sessionCookie);
}

export async function currentSessionToken(): Promise<string | null> {
  return (await cookies()).get(config.sessionCookie)?.value ?? null;
}
