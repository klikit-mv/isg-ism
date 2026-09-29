import Link from 'next/link';
import { and, asc, inArray, isNull } from 'drizzle-orm';
import { forbidden, notFound } from 'next/navigation';
import { db, schema } from '@/db';
import { PageHeader } from '@/components/PageHeader';
import { ConfirmButton } from '@/components/ConfirmButton';
import { deleteGroupAction } from '@/app/actions/groups';
import { findGroupByUuid, groupMembership } from '@/server/groups';
import { canAccessGroup } from '@/server/scope';
import { requireUser } from '@/server/session';
import { isActive, isAdmin, isLeader } from '@/server/users';
import { GroupForms } from './GroupForms';

export default async function GroupPage({ params }: { params: Promise<{ uuid: string }> }) {
  const user = await requireUser();
  const group = await findGroupByUuid((await params).uuid);
  if (!group) notFound();
  if (!isActive(user) || (!isAdmin(user) && !isLeader(user)) || !(await canAccessGroup(user, group.id))) forbidden();

  const S = schema.students;
  const [membership, students, leaderRows] = await Promise.all([
    groupMembership(group.id),
    db.select({ id: S.id, name: S.name, section: S.section, indexNumber: S.indexNumber }).from(S).where(isNull(S.deletedAt)).orderBy(asc(S.name)),
    db.select({ id: schema.userRoles.userId }).from(schema.userRoles).where(inArray(schema.userRoles.role, ['leader', 'admin'])),
  ]);
  const leaderUsers = leaderRows.length
    ? await db.select({ id: schema.users.id, name: schema.users.name, nationalId: schema.users.nationalId }).from(schema.users)
        .where(and(inArray(schema.users.id, [...new Set(leaderRows.map((r) => r.id))]), isNull(schema.users.deletedAt), inArray(schema.users.status, ['active']))).orderBy(asc(schema.users.name))
    : [];
  const pool = group.section ? students.filter((s) => s.section === group.section) : students;

  return (
    <>
      <PageHeader title={group.name} description={`${group.type || 'Group'} · ${group.section ?? 'Mixed sections'} · ${membership.members.length} members`}>
        <Link href="/groups" className="btn-secondary">All groups</Link>
        {isAdmin(user) && <ConfirmButton action={deleteGroupAction} fields={{ uuid: group.uuid }} label="Delete group" size="md" message="Delete this group? Scouts stay in the registry." confirm="Delete" />}
      </PageHeader>
      <GroupForms
        group={{ uuid: group.uuid, name: group.name, type: group.type, section: group.section }}
        selected={membership}
        memberOptions={pool.map((s) => ({ id: s.id, label: s.name, hint: s.section }))}
        leaderOptions={leaderUsers.map((u) => ({ id: u.id, label: u.name, hint: u.nationalId }))}
        roverOptions={students.filter((s) => s.section === 'Rover').map((s) => ({ id: s.id, label: s.name, hint: s.indexNumber }))}
      />
    </>
  );
}
