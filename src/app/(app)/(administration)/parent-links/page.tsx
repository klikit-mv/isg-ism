import { and, asc, count, desc, eq, isNull, like, or } from 'drizzle-orm';
import { alias } from 'drizzle-orm/mysql-core';
import { db, schema } from '@/db';
import { Badge } from '@/components/Badge';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { currentPage, PAGE_SIZE, pageOf, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { ModalButton } from '@/components/Modal';
import { Table } from '@/components/Table';
import { ParentLinkStatus } from '@/lib/enums';
import { updateLinkStatusAction } from '@/app/actions/parent-links';
import { CreateLinkModal } from './CreateLinkModal';
import { SubmitButton } from '@/components/form/ActionForm';

export const metadata = { title: 'Parent links' };

export default async function ParentLinksPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const sp = await searchParams;
  const page = currentPage(sp.page);
  const Lk = schema.parentStudentLinks;
  const P = alias(schema.users, 'p');
  const S = schema.students;
  const term = sp.q?.trim();
  const like_ = term ? `%${term}%` : null;
  const where = and(
    sp.status ? eq(Lk.status, sp.status) : undefined,
    like_ ? or(like(P.name, like_), like(P.nationalId, like_), like(S.name, like_), like(S.nationalId, like_)) : undefined,
  );
  const base = db.select({ n: count() }).from(Lk).innerJoin(P, eq(P.id, Lk.parentUserId)).innerJoin(S, eq(S.id, Lk.studentId));
  const [{ n }] = await base.where(where);
  const rows = await db.select({ link: Lk, parentName: P.name, parentNid: P.nationalId, studentName: S.name, studentNid: S.nationalId }).from(Lk)
    .innerJoin(P, eq(P.id, Lk.parentUserId)).innerJoin(S, eq(S.id, Lk.studentId)).where(where)
    .orderBy(desc(Lk.createdAt), desc(Lk.id)).limit(PAGE_SIZE).offset((page - 1) * PAGE_SIZE);
  const [parents, students] = await Promise.all([
    db.select({ id: schema.users.id, name: schema.users.name, nid: schema.users.nationalId }).from(schema.users).where(isNull(schema.users.deletedAt)).orderBy(asc(schema.users.name)),
    db.select({ id: S.id, name: S.name, nid: S.nationalId }).from(S).where(isNull(S.deletedAt)).orderBy(asc(S.name)),
  ]);
  const data = pageOf(rows, Number(n), page);

  return (
    <>
      <PageHeader title="Parent links" description="Which parent can see which scout. A scout can have only one pending or approved parent.">
        <ModalButton name="create-link">Link parent</ModalButton>
      </PageHeader>
      <Filters action="/parent-links">
        <FilterInput name="q" label="Search" value={sp.q} placeholder="Parent or scout" />
        <FilterSelect name="status" label="Status" value={sp.status} options={ParentLinkStatus.options()} placeholder="Any status" />
      </Filters>
      {data.rows.length === 0 ? <Empty message="No parent links yet." /> : (
        <>
          <Table headers={['Parent', 'Scout', 'Status', 'Change status']}>
            {data.rows.map((r) => (
              <tr key={r.link.id}>
                <td data-label="Parent">{r.parentName} <span className="text-xs text-gray-400">{r.parentNid}</span></td>
                <td data-label="Scout">{r.studentName} <span className="text-xs text-gray-400">{r.studentNid}</span></td>
                <td data-label="Status"><Badge of={ParentLinkStatus} value={r.link.status} /></td>
                <td data-label="Change status">
                  <form action={updateLinkStatusAction} className="flex items-center justify-end gap-2 md:justify-start">
                    <input type="hidden" name="id" value={r.link.id} />
                    <select name="status" defaultValue={r.link.status} className="input !w-auto !py-1 text-xs">
                      {ParentLinkStatus.options().map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                    <SubmitButton className="btn-secondary btn-sm">Save</SubmitButton>
                  </form>
                </td>
              </tr>
            ))}
          </Table>
          <Pagination page={data.page} pages={data.pages} path="/parent-links" query={{ q: sp.q, status: sp.status }} />
        </>
      )}
      <CreateLinkModal
        parents={parents.map((u) => ({ value: String(u.id), label: `${u.name} (${u.nid})` }))}
        students={students.map((s) => ({ value: String(s.id), label: `${s.name} (${s.nid})` }))}
      />
    </>
  );
}
