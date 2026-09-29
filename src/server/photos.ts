import { eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import { recordAudit } from './audit';
import { remove } from './storage';
import { storeImage } from './uploads';
import type { AuthUser } from './users';

/** Scout photos: stored on the public disk under students/. */
export async function assignStudentPhoto(student: { id: number; uuid: string; photoPath: string | null }, file: File, actor: AuthUser | null): Promise<void> {
  const path = await storeImage(file, { directory: 'students', name: `${student.uuid}-${Date.now().toString(36)}`, field: 'photo' });
  await db.update(schema.students).set({ photoPath: path, updatedAt: new Date() }).where(eq(schema.students.id, student.id));
  await remove('public', student.photoPath);
  await recordAudit('student.photo_updated', { type: 'student', id: student.id }, {}, actor?.id ?? null);
}

export async function clearStudentPhoto(student: { id: number; photoPath: string | null }, actor: AuthUser | null): Promise<void> {
  await db.update(schema.students).set({ photoPath: null, updatedAt: new Date() }).where(eq(schema.students.id, student.id));
  await remove('public', student.photoPath);
  await recordAudit('student.photo_removed', { type: 'student', id: student.id }, {}, actor?.id ?? null);
}
