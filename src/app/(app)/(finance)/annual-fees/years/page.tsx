import Link from 'next/link';
import { count, desc } from 'drizzle-orm';
import { forbidden } from 'next/navigation';
import { db, schema } from '@/db';
import { Badge } from '@/components/Badge';
import { ActionButton } from '@/components/ConfirmButton';
import { ModalButton } from '@/components/Modal';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { RecordStatus } from '@/lib/enums';
import { formatMoney } from '@/lib/money';
import { yearStatusAction } from '@/app/actions/annual-fees';
import { suggestedYear } from '@/server/annual-fees';
import { requireUser } from '@/server/session';
import { hasPermission, isAdmin } from '@/server/users';
import { CreateYearModal } from './CreateYearModal';

export const metadata = { title: 'Annual fee years' };

export default async function FeeYearsPage() {
  const user = await requireUser();
  if (!hasPermission(user, 'canManageFees')) forbidden();
  const Y = schema.annualFeeYears;
  const [years, counts, suggested] = await Promise.all([
    db.select().from(Y).orderBy(desc(Y.year)),
    db.select({ id: schema.annualFees.annualFeeYearId, n: count() }).from(schema.annualFees).groupBy(schema.annualFees.annualFeeYearId),
    suggestedYear(),
  ]);
  const admin = isAdmin(user);
  return (
    <>
      <PageHeader title="Annual fee years" description="Open a year, then generate invoices for scouts and leaders.">
        <Link href="/annual-fees" className="btn-secondary">Annual fees</Link>
        {admin && <ModalButton name="create-year">Create year</ModalButton>}
      </PageHeader>
      {years.length === 0 ? <Empty message="No fee years yet." /> : (
        <Table headers={['Year', 'Amount', 'Invoices', 'Status', '']}>
          {years.map((y) => (
            <tr key={y.id}>
              <td data-label="Year" className="font-medium">{y.year}</td>
              <td data-label="Amount">{formatMoney(y.amount)}</td>
              <td data-label="Invoices">{Number(counts.find((c) => c.id === y.id)?.n ?? 0)}</td>
              <td data-label="Status"><Badge of={RecordStatus} value={y.status} /></td>
              <td className="whitespace-nowrap text-right">
                <div className="flex flex-wrap justify-end gap-2">
                  {y.status === 'Active' && <Link href={`/annual-fees/years/${y.year}/generate`} className="btn-primary btn-sm">Generate invoices</Link>}
                  {admin && <ActionButton action={yearStatusAction} label={y.status === 'Active' ? 'Deactivate' : 'Activate'} fields={{ year: String(y.year), status: y.status === 'Active' ? 'Inactive' : 'Active' }} />}
                </div>
              </td>
            </tr>
          ))}
        </Table>
      )}
      {admin && <CreateYearModal suggested={suggested} />}
    </>
  );
}
