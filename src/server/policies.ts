import { canAccessStudent } from './scope';
import { isActive, isAdmin, isLeader, type AuthUser } from './users';

/**
 * Who may do what. Admins pass everything, like the old Gate::before.
 * Leaders are limited to the groups they lead (see scope.ts).
 */
export const canVerifyRegistrations = (u: AuthUser) => isAdmin(u) || (isActive(u) && isLeader(u));
export const canImport = (u: AuthUser) => isAdmin(u) || (isActive(u) && isLeader(u));
export const canViewStudent = async (u: AuthUser, s: { id: number; status: string }) => isAdmin(u) || (isActive(u) && (await canAccessStudent(u, s)));
export const canChangePhoto = async (u: AuthUser, s: { id: number; status: string }) =>
  isAdmin(u) || (isActive(u) && isLeader(u) && (await canAccessStudent(u, s)));
export const canManageStudents = (u: AuthUser) => isAdmin(u);
