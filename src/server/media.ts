import { eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import { exists } from './storage';
import type { AuthUser } from './users';

/**
 * URL for a stored image (login-protected). Photos live on the public disk
 * but are only served to signed-in people.
 */
export const mediaUrl = (path: string | null | undefined): string | null => (path ? `/media/${path.split('/').map(encodeURIComponent).join('/')}` : null);

/** Profile picture: the uploaded avatar, else the linked scout's photo. */
export async function avatarUrl(user: Pick<AuthUser, 'avatarPath' | 'studentId'>): Promise<string | null> {
  if (user.avatarPath && (await exists('public', user.avatarPath))) return mediaUrl(user.avatarPath);
  if (user.studentId) {
    const [s] = await db.select({ p: schema.students.photoPath }).from(schema.students).where(eq(schema.students.id, user.studentId)).limit(1);
    if (s?.p && (await exists('public', s.p))) return mediaUrl(s.p);
  }
  return null;
}
