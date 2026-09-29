import { and, eq, inArray } from 'drizzle-orm';
import { db, schema } from '@/db';

export async function findChildByNationalId(nationalId: string) {
  const [row] = await db.select({ id: schema.students.id, nationalId: schema.students.nationalId, name: schema.students.name, section: schema.students.section })
    .from(schema.students).where(eq(schema.students.nationalId, nationalId)).limit(1);
  return row ?? null;
}

export async function activeLinkExists(studentId: number): Promise<boolean> {
  const [row] = await db.select({ id: schema.parentStudentLinks.id }).from(schema.parentStudentLinks)
    .where(and(eq(schema.parentStudentLinks.studentId, studentId), inArray(schema.parentStudentLinks.status, ['pending', 'approved']))).limit(1);
  return !!row;
}
