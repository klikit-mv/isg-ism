import { and, eq, inArray, isNull } from 'drizzle-orm';
import bcrypt from 'bcryptjs';
import { db, schema, type DbLike } from '@/db';
import { PermissionValue, RoleValue } from '@/lib/enums';

export type UserRow = typeof schema.users.$inferSelect;

/** A signed-in person with roles and permissions loaded. */
export interface AuthUser extends UserRow {
  roles: RoleValue[];
  permissions: PermissionValue[];
}

export const isActive = (u: Pick<UserRow, 'status'>) => u.status === 'active';
export const hasRole = (u: AuthUser, role: RoleValue) => u.roles.includes(role);
export const hasAnyRole = (u: AuthUser, roles: readonly RoleValue[]) => roles.some((r) => hasRole(u, r));
export const isAdmin = (u: AuthUser) => isActive(u) && hasRole(u, 'admin');
export const isLeader = (u: AuthUser) => hasRole(u, 'leader');
export const isStaff = (u: AuthUser) => isAdmin(u) || isLeader(u);
/** Explicit grant, or implicit for admins. */
export const hasPermission = (u: AuthUser, p: PermissionValue) => isAdmin(u) || u.permissions.includes(p);

export { initials } from '@/lib/format';

export async function loadUsers(ids: number[], conn: DbLike = db): Promise<AuthUser[]> {
  if (ids.length === 0) return [];
  const rows = await conn.select().from(schema.users).where(and(inArray(schema.users.id, ids), isNull(schema.users.deletedAt)));
  const roles = await conn.select().from(schema.userRoles).where(inArray(schema.userRoles.userId, ids));
  const perms = await conn.select().from(schema.userPermissions).where(inArray(schema.userPermissions.userId, ids));
  return rows.map((row) => ({
    ...row,
    roles: roles.filter((r) => r.userId === row.id).map((r) => r.role as RoleValue),
    permissions: perms.filter((p) => p.userId === row.id).map((p) => p.permission as PermissionValue),
  }));
}

export async function loadUser(id: number, conn: DbLike = db): Promise<AuthUser | null> {
  return (await loadUsers([id], conn))[0] ?? null;
}

export async function loadUserByNationalId(nationalId: string, conn: DbLike = db): Promise<AuthUser | null> {
  const [row] = await conn.select({ id: schema.users.id }).from(schema.users)
    .where(and(eq(schema.users.nationalId, nationalId), isNull(schema.users.deletedAt))).limit(1);
  return row ? loadUser(row.id, conn) : null;
}

export async function loadUserByUuid(uuid: string, conn: DbLike = db): Promise<AuthUser | null> {
  const [row] = await conn.select({ id: schema.users.id }).from(schema.users)
    .where(and(eq(schema.users.uuid, uuid), isNull(schema.users.deletedAt))).limit(1);
  return row ? loadUser(row.id, conn) : null;
}

const rounds = () => Number(process.env.BCRYPT_ROUNDS ?? 12);
export const hashPin = (pin: string) => bcrypt.hash(pin, rounds());
export const verifyPin = (pin: string, hash: string) => bcrypt.compare(pin, hash);

export async function assignRole(userId: number, role: RoleValue, conn: DbLike = db): Promise<void> {
  await conn.insert(schema.userRoles).values({ userId, role, createdAt: new Date(), updatedAt: new Date() }).onDuplicateKeyUpdate({ set: { role } });
}

export async function syncRoles(userId: number, roles: RoleValue[], conn: DbLike = db): Promise<void> {
  const unique = [...new Set(roles)];
  const existing = await conn.select().from(schema.userRoles).where(eq(schema.userRoles.userId, userId));
  const remove = existing.filter((r) => !unique.includes(r.role as RoleValue)).map((r) => r.id);
  if (remove.length) await conn.delete(schema.userRoles).where(inArray(schema.userRoles.id, remove));
  for (const role of unique) await assignRole(userId, role, conn);
}

export async function syncPermissions(userId: number, permissions: PermissionValue[], conn: DbLike = db): Promise<void> {
  const unique = [...new Set(permissions)];
  const existing = await conn.select().from(schema.userPermissions).where(eq(schema.userPermissions.userId, userId));
  const remove = existing.filter((r) => !unique.includes(r.permission as PermissionValue)).map((r) => r.id);
  if (remove.length) await conn.delete(schema.userPermissions).where(inArray(schema.userPermissions.id, remove));
  for (const permission of unique) {
    await conn.insert(schema.userPermissions).values({ userId, permission, createdAt: new Date(), updatedAt: new Date() }).onDuplicateKeyUpdate({ set: { permission } });
  }
}

/** Students a parent may see (approved links only). */
export async function approvedChildIds(userId: number, conn: DbLike = db): Promise<number[]> {
  const rows = await conn.select({ id: schema.parentStudentLinks.studentId }).from(schema.parentStudentLinks)
    .where(and(eq(schema.parentStudentLinks.parentUserId, userId), eq(schema.parentStudentLinks.status, 'approved')));
  return rows.map((r) => r.id);
}

/** Users who can act as staff (active admins and leaders). */
export async function activeStaff(conn: DbLike = db): Promise<AuthUser[]> {
  const rows = await conn.select({ id: schema.userRoles.userId }).from(schema.userRoles)
    .where(inArray(schema.userRoles.role, ['admin', 'leader']));
  const users = await loadUsers([...new Set(rows.map((r) => r.id))], conn);
  return users.filter(isActive);
}

/** Active users holding a permission (admins hold every permission). */
export async function usersWithPermission(permission: PermissionValue, conn: DbLike = db): Promise<AuthUser[]> {
  const granted = await conn.select({ id: schema.userPermissions.userId }).from(schema.userPermissions).where(eq(schema.userPermissions.permission, permission));
  const admins = await conn.select({ id: schema.userRoles.userId }).from(schema.userRoles).where(eq(schema.userRoles.role, 'admin'));
  const users = await loadUsers([...new Set([...granted, ...admins].map((r) => r.id))], conn);
  return users.filter(isActive).filter((u) => hasPermission(u, permission));
}
