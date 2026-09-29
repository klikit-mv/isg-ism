import { and, eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import type { PermissionValue, RoleValue } from '@/lib/enums';
import { recordAudit } from './audit';
import { normalizeNationalId } from './auth';
import { ScoutError } from './errors';
import { notify } from './notifications';
import { setLinkStatus } from './parent-links';
import { hashPin, loadUser, syncPermissions, syncRoles, type AuthUser } from './users';
import { randomBytes } from 'node:crypto';

export interface UserInput {
  name: string;
  nationalId: string;
  email?: string | null;
  pin: string;
  status?: 'active' | 'inactive';
  roles?: RoleValue[];
  permissions?: PermissionValue[];
  studentId?: number | null;
}

export async function createUser(data: UserInput, actor: AuthUser): Promise<AuthUser> {
  const now = new Date();
  const id = await db.transaction(async (tx) => {
    const [row] = await tx.insert(schema.users).values({
      name: data.name, nationalId: normalizeNationalId(data.nationalId), email: data.email ?? null, password: await hashPin(data.pin),
      status: data.status ?? 'active', studentId: data.studentId ?? null, verifiedAt: now, verifiedBy: actor.id, createdAt: now, updatedAt: now,
    }).$returningId();
    await syncRoles(row.id, data.roles ?? [], tx);
    await syncPermissions(row.id, data.permissions ?? [], tx);
    await recordAudit('user.created', { type: 'user', id: row.id }, { national_id: normalizeNationalId(data.nationalId), roles: data.roles ?? [], permissions: data.permissions ?? [] }, actor.id, tx);
    return row.id;
  });
  return (await loadUser(id))!;
}

/** Update name, email, status, roles, permissions and linked scout. The PIN is never part of an edit. */
export async function updateUser(userId: number, data: Omit<UserInput, 'pin' | 'nationalId'>, actor: AuthUser): Promise<void> {
  await db.transaction(async (tx) => {
    await tx.update(schema.users).set({
      name: data.name, email: data.email ?? null, status: data.status ?? 'active', studentId: data.studentId ?? null, updatedAt: new Date(),
    }).where(eq(schema.users.id, userId));
    await syncRoles(userId, data.roles ?? [], tx);
    await syncPermissions(userId, data.permissions ?? [], tx);
    await recordAudit('user.updated', { type: 'user', id: userId }, { status: data.status, roles: data.roles ?? [], permissions: data.permissions ?? [] }, actor.id, tx);
  });
}

/** Reset a PIN: clears legacy hashes and ends every session for that user. */
export async function resetPin(userId: number, pin: string, actor: AuthUser): Promise<void> {
  await db.transaction(async (tx) => {
    await tx.update(schema.users).set({
      password: await hashPin(pin), legacyPinHash: null, legacyPinSalt: null, rememberToken: randomBytes(30).toString('hex'), updatedAt: new Date(),
    }).where(eq(schema.users.id, userId));
    await tx.delete(schema.appSessions).where(eq(schema.appSessions.userId, userId));
    await recordAudit('user.pin_reset', { type: 'user', id: userId }, {}, actor.id, tx);
  });
}

export async function setUserStatus(userId: number, status: 'active' | 'inactive', actor: AuthUser): Promise<void> {
  await db.update(schema.users).set({ status, updatedAt: new Date() }).where(eq(schema.users.id, userId));
  await recordAudit('user.status_changed', { type: 'user', id: userId }, { status }, actor.id);
}

export async function deleteUser(userId: number, actor: AuthUser): Promise<void> {
  if (userId === actor.id) throw new ScoutError('You cannot delete your own account.');
  const target = await loadUser(userId);
  if (!target) return;
  await recordAudit('user.deleted', { type: 'user', id: userId }, { national_id: target.nationalId }, actor.id);
  await db.update(schema.users).set({ deletedAt: new Date() }).where(eq(schema.users.id, userId));
  await db.delete(schema.appSessions).where(eq(schema.appSessions.userId, userId));
}

export async function verifyParentRegistration(parentId: number, actor: AuthUser): Promise<void> {
  const now = new Date();
  await db.update(schema.users).set({ status: 'active', verifiedAt: now, verifiedBy: actor.id, updatedAt: now }).where(eq(schema.users.id, parentId));
  const pending = await db.select().from(schema.parentStudentLinks)
    .where(and(eq(schema.parentStudentLinks.parentUserId, parentId), eq(schema.parentStudentLinks.status, 'pending')));
  for (const link of pending) await setLinkStatus(link.id, 'approved', actor);
  await recordAudit('parent.verified', { type: 'user', id: parentId }, {}, actor.id);
  const parent = await loadUser(parentId);
  if (parent) await notify.parentVerified(parent);
}

export async function rejectParentRegistration(parentId: number, actor: AuthUser): Promise<void> {
  const now = new Date();
  await db.transaction(async (tx) => {
    await tx.update(schema.users).set({ status: 'inactive', verifiedAt: now, verifiedBy: actor.id, updatedAt: now }).where(eq(schema.users.id, parentId));
    await tx.update(schema.parentStudentLinks).set({ status: 'rejected', updatedAt: now })
      .where(and(eq(schema.parentStudentLinks.parentUserId, parentId), eq(schema.parentStudentLinks.status, 'pending')));
    await recordAudit('parent.declined', { type: 'user', id: parentId }, {}, actor.id, tx);
  });
}
