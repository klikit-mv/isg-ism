import { and, eq, inArray, ne } from 'drizzle-orm';
import { db, schema, type DbLike } from '@/db';
import { ParentLinkStatus } from '@/lib/enums';
import { recordAudit } from './audit';
import { ScoutError } from './errors';
import { assignRole, type AuthUser } from './users';

type Link = typeof schema.parentStudentLinks.$inferSelect;
const OPEN = ['pending', 'approved'];

/** The open link that already gives this scout a parent, if any. */
async function activeLinkFor(studentId: number, exceptLinkId?: number, conn: DbLike = db): Promise<Link | null> {
  const [row] = await conn.select().from(schema.parentStudentLinks)
    .where(and(
      eq(schema.parentStudentLinks.studentId, studentId),
      inArray(schema.parentStudentLinks.status, OPEN),
      ...(exceptLinkId ? [ne(schema.parentStudentLinks.id, exceptLinkId)] : []),
    )).limit(1);
  return row ?? null;
}

export async function assertStudentHasNoOtherParent(studentId: number, exceptLinkId?: number, conn: DbLike = db): Promise<void> {
  if (await activeLinkFor(studentId, exceptLinkId, conn)) throw new ScoutError('This scout is already added under another parent.');
}

export async function createLink(
  parent: Pick<AuthUser, 'id' | 'nationalId'>,
  student: { id: number; nationalId: string },
  status: 'pending' | 'approved' | 'rejected' | 'inactive' = 'approved',
  actor: AuthUser | null = null,
): Promise<Link> {
  return db.transaction(async (tx) => {
    if (OPEN.includes(status)) await assertStudentHasNoOtherParent(student.id, undefined, tx);

    const [existing] = await tx.select().from(schema.parentStudentLinks)
      .where(and(eq(schema.parentStudentLinks.parentUserId, parent.id), eq(schema.parentStudentLinks.studentId, student.id))).limit(1);
    let link: Link;
    if (existing) {
      await tx.update(schema.parentStudentLinks).set({ status, updatedAt: new Date() }).where(eq(schema.parentStudentLinks.id, existing.id));
      link = { ...existing, status };
    } else {
      const [res] = await tx.insert(schema.parentStudentLinks).values({
        parentUserId: parent.id, studentId: student.id, status, createdAt: new Date(), updatedAt: new Date(),
      }).$returningId();
      [link] = await tx.select().from(schema.parentStudentLinks).where(eq(schema.parentStudentLinks.id, res.id));
    }

    await assignRole(parent.id, 'parent', tx);
    await recordAudit('parent_link.created', { type: 'parent_link', id: link.id }, { parent: parent.nationalId, student: student.nationalId, status }, actor?.id ?? null, tx);
    return link;
  });
}

export async function setLinkStatus(linkId: number, status: 'pending' | 'approved' | 'rejected' | 'inactive', actor: AuthUser): Promise<void> {
  await db.transaction(async (tx) => {
    const [link] = await tx.select().from(schema.parentStudentLinks).where(eq(schema.parentStudentLinks.id, linkId)).limit(1);
    if (!link) throw new ScoutError('That link no longer exists.');
    if (OPEN.includes(status)) await assertStudentHasNoOtherParent(link.studentId, link.id, tx);
    await tx.update(schema.parentStudentLinks).set({ status, updatedAt: new Date() }).where(eq(schema.parentStudentLinks.id, link.id));
    await recordAudit('parent_link.status_changed', { type: 'parent_link', id: link.id }, { from: link.status, to: status }, actor.id, tx);
  });
}

export const linkTone = (status: string) => ParentLinkStatus.tone(status);
