import { eq } from 'drizzle-orm';
import { notFound } from 'next/navigation';
import { db, schema } from '@/db';
import { currentPage } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { RECORD_TABS, StudentRecord, type RecordTab } from '@/components/StudentRecord';
import { requireUser } from '@/server/session';

export const metadata = { title: 'My details' };

export default async function SelfPage({ params, searchParams }: { params: Promise<{ tab?: string[] }>; searchParams: Promise<{ page?: string }> }) {
  const [{ tab = [] }, sp, user] = await Promise.all([params, searchParams, requireUser()]);
  if (tab.length > 1 || (tab[0] && !(tab[0] in RECORD_TABS))) notFound();
  const [student] = user.studentId ? await db.select().from(schema.students).where(eq(schema.students.id, user.studentId)).limit(1) : [];
  if (!student) {
    return (
      <>
        <PageHeader title="My record" />
        <Empty message="Your account is not linked to a scout record yet. Please ask a leader to link it." />
      </>
    );
  }
  return <StudentRecord viewer={user} student={student} tab={(tab[0] ?? 'profile') as RecordTab} context="self" page={currentPage(sp.page)} />;
}
