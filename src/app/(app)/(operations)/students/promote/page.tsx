import { and, asc, eq, isNull, like, or, exists } from 'drizzle-orm';
import { forbidden } from 'next/navigation';
import { db, schema } from '@/db';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { Empty, PageHeader } from '@/components/PageHeader';
import { ScoutSection, StudentStatus, sectionNext, type ScoutSectionValue } from '@/lib/enums';
import { canManageStudents } from '@/server/policies';
import { requireUser } from '@/server/session';
import { PromoteForm } from './PromoteForm';

export const metadata = { title: 'Section promotion' };

export default async function PromotionPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  if (!canManageStudents(await requireUser())) forbidden();
  const sp = await searchParams;
  const from = (ScoutSection.is(sp.from) ? sp.from : 'Pre Cub') as ScoutSectionValue;
  const to = sectionNext(from);
  const S = schema.students;
  const term = sp.q?.trim();

  const [candidates, groups] = await Promise.all([
    db.select().from(S).where(and(
      isNull(S.deletedAt), eq(S.section, from),
      term ? or(like(S.name, `%${term}%`), like(S.nationalId, `%${term}%`), like(S.indexNumber, `%${term}%`)) : undefined,
      sp.status ? eq(S.status, sp.status) : undefined,
      sp.patrol ? like(S.patrol, `%${sp.patrol}%`) : undefined,
      sp.group ? exists(db.select({ x: schema.groupMembers.id }).from(schema.groupMembers).innerJoin(schema.groups, eq(schema.groups.id, schema.groupMembers.groupId))
        .where(and(eq(schema.groupMembers.studentId, S.id), eq(schema.groups.uuid, sp.group)))) : undefined,
    )).orderBy(asc(S.name)),
    db.select({ uuid: schema.groups.uuid, name: schema.groups.name }).from(schema.groups).where(isNull(schema.groups.deletedAt)).orderBy(asc(schema.groups.name)),
  ]);

  const fromOptions = ScoutSection.values.filter((s) => sectionNext(s)).map((s) => ({ value: s, label: `${s} → ${sectionNext(s)}` }));
  return (
    <>
      <PageHeader title="Section promotion" description="Graduate scouts one section forward. Issued certificates are not changed." />
      <Filters action="/students/promote">
        <div>
          <label htmlFor="f-from" className="label">From section</label>
          <select id="f-from" name="from" defaultValue={from} className="input">{fromOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}</select>
        </div>
        <FilterInput name="q" label="Name" value={sp.q} />
        <FilterSelect name="status" label="Status" value={sp.status} options={StudentStatus.options()} placeholder="Any status" />
        <FilterInput name="patrol" label="Patrol" value={sp.patrol} />
        <FilterSelect name="group" label="Group" value={sp.group} options={groups.map((g) => ({ value: g.uuid, label: g.name }))} placeholder="Any group" />
      </Filters>
      {to === null ? <Empty message="Rovers are the last section." /> : candidates.length === 0 ? <Empty message={`No ${from} scouts match these filters.`} /> : (
        <PromoteForm from={from} to={to} rows={candidates.map((s) => ({ id: s.id, name: s.name, indexNumber: s.indexNumber, patrol: s.patrol, status: s.status }))} statusBadges={Object.fromEntries(candidates.map((s) => [s.id, StudentStatus.label(s.status)]))} />
      )}
    </>
  );
}
