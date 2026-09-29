import Link from 'next/link';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { Badge } from '@/components/Badge';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Stat } from '@/components/Stat';
import { Table } from '@/components/Table';
import { PayButton } from '@/components/finance/PayButton';
import { PaymentModal } from '@/components/finance/PaymentModal';
import { FeeStatus, PersonType, ScoutSection } from '@/lib/enums';
import { formatMoney } from '@/lib/money';
import { annualYearOptions, listAnnualFees } from '@/server/annual-fees';
import { requireUser } from '@/server/session';
import { hasPermission } from '@/server/users';

export const metadata = { title: 'Annual fees' };

export default async function AnnualFeesPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  const sp = await searchParams;
  const [{ page, stats }, years] = await Promise.all([
    listAnnualFees(user, { q: sp.q, year: sp.year, section: sp.section, status: sp.status, page: currentPage(sp.page) }),
    annualYearOptions(),
  ]);
  return (
    <>
      <PageHeader title="Annual fees" description="Yearly membership fees for scouts and leaders.">
        {hasPermission(user, 'canManageFees') && <Link href="/annual-fees/years" className="btn-secondary">Fee years</Link>}
      </PageHeader>
      <div className="mb-4 grid grid-cols-3 gap-3">
        <Stat label="Records" value={stats.records} />
        <Stat label="Fully paid" value={stats.paid} tone="gold" />
        <Stat label="Total billed" value={formatMoney(stats.billed)} />
      </div>
      <Filters action="/annual-fees">
        <FilterInput name="q" label="Name or National ID" value={sp.q} />
        <FilterSelect name="year" label="Year" value={sp.year} options={years.map((y) => ({ value: y, label: y }))} placeholder="Any year" />
        <FilterSelect name="section" label="Section" value={sp.section} options={ScoutSection.options()} placeholder="Any section" />
        <FilterSelect name="status" label="Status" value={sp.status} options={FeeStatus.options().filter((o) => o.value !== 'Void')} placeholder="Any status" />
      </Filters>
      {page.rows.length === 0 ? <Empty message="No annual fees match these filters." /> : (
        <>
          <Table headers={['Year', 'Person', 'Type', 'Section', 'Fee', 'Paid', 'Outstanding', 'Status', '']}>
            {page.rows.map(({ fee, student, studentSection, user: leader, year }) => (
              <tr key={fee.id}>
                <td data-label="Year">{year}</td>
                <td data-label="Person" className="font-medium">{student ?? leader}</td>
                <td data-label="Type">{PersonType.label(fee.personType)}</td>
                <td data-label="Section">{fee.section ?? studentSection ?? '—'}</td>
                <td data-label="Fee">{formatMoney(fee.amount)}</td>
                <td data-label="Paid">{formatMoney(fee.paidAmount)}</td>
                <td data-label="Outstanding">{formatMoney(fee.outstandingAmount)}</td>
                <td data-label="Status"><Badge of={FeeStatus} value={fee.status} /></td>
                <td className="text-right">
                  <PayButton payable={{ type: 'annual_fee', id: fee.id, uuid: fee.uuid, studentId: fee.studentId, userId: fee.userId, amountDue: fee.amount, paidAmount: fee.paidAmount, outstandingAmount: fee.outstandingAmount, status: fee.status, closed: false, description: `Annual fee ${year}` }} />
                </td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/annual-fees" query={{ q: sp.q, year: sp.year, section: sp.section, status: sp.status }} />
        </>
      )}
      <PaymentModal />
    </>
  );
}
