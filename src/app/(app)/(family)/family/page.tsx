import Link from 'next/link';
import { asc, inArray } from 'drizzle-orm';
import { db, schema } from '@/db';
import { Badge } from '@/components/Badge';
import { Empty, PageHeader } from '@/components/PageHeader';
import { StudentAvatar } from '@/components/StudentAvatar';
import { ScoutSection, StudentStatus } from '@/lib/enums';
import { requireUser } from '@/server/session';
import { approvedChildIds } from '@/server/users';

export const metadata = { title: 'My students' };

export default async function FamilyPage() {
  const user = await requireUser();
  const ids = await approvedChildIds(user.id);
  const children = ids.length ? await db.select().from(schema.students).where(inArray(schema.students.id, ids)).orderBy(asc(schema.students.name)) : [];
  return (
    <>
      <PageHeader title="My students" description="Children linked to your account." />
      {children.length === 0 ? <Empty message="No children are linked to your account yet. Links appear once a leader approves them." /> : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {children.map((c) => (
            <Link key={c.id} href={`/family/students/${c.uuid}`} className="card flex items-center gap-4 hover:border-navy-300">
              <StudentAvatar student={c} className="h-14 w-14" />
              <div>
                <div className="font-semibold">{c.name}</div>
                <div className="mt-1 flex flex-wrap gap-1"><Badge of={ScoutSection} value={c.section} /><Badge of={StudentStatus} value={c.status} /></div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}
