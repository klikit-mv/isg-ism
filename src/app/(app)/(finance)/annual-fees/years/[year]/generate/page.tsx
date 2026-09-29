import Link from 'next/link';
import { eq } from 'drizzle-orm';
import { forbidden, notFound } from 'next/navigation';
import { db, schema } from '@/db';
import { PageHeader } from '@/components/PageHeader';
import { formatMoney } from '@/lib/money';
import { invoicePeople } from '@/server/annual-fees';
import { requireUser } from '@/server/session';
import { hasPermission } from '@/server/users';
import { GenerateForm } from './GenerateForm';

export default async function GeneratePage({ params, searchParams }: { params: Promise<{ year: string }>; searchParams: Promise<{ inactive?: string }> }) {
  const user = await requireUser();
  if (!hasPermission(user, 'canManageFees')) forbidden();
  const { year: yearParam } = await params;
  const [year] = await db.select().from(schema.annualFeeYears).where(eq(schema.annualFeeYears.year, Number(yearParam))).limit(1);
  if (!year) notFound();
  const inactive = (await searchParams).inactive === '1';
  const people = await invoicePeople(year, inactive);
  return (
    <>
      <PageHeader title={`Generate ${year.year} invoices`} description={`${formatMoney(year.amount)} each. People already invoiced are skipped.`}>
        <Link href="/annual-fees/years" className="btn-secondary">Back</Link>
        <Link href={`/annual-fees/years/${year.year}/generate${inactive ? '' : '?inactive=1'}`} className="btn-secondary">{inactive ? 'Hide inactive' : 'Include inactive'}</Link>
      </PageHeader>
      <GenerateForm year={year.year} yearLabel={String(year.year)} people={people} />
    </>
  );
}
