import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { Badge } from '@/components/Badge';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Stat } from '@/components/Stat';
import { Table } from '@/components/Table';
import { PayButton } from '@/components/finance/PayButton';
import { PaymentModal } from '@/components/finance/PaymentModal';
import { FeeStatus, ScoutSection } from '@/lib/enums';
import { formatDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import { listClassFees } from '@/server/finance-queries';
import { requireUser } from '@/server/session';

export const metadata = { title: 'Class fees' };

const statusOptions = FeeStatus.options().filter((o) => o.value !== 'Void');

export default async function ClassFeesPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  const sp = await searchParams;
  const { page, stats, activities } = await listClassFees(user, { q: sp.q, activity: sp.activity, section: sp.section, status: sp.status, page: currentPage(sp.page) });
  return (
    <>
      <PageHeader title="Class fees" description="Fees created from attendance on charged activities." />
      <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Stat label="Records" value={stats.records} />
        <Stat label="Fully paid" value={stats.paid} tone="gold" />
        <Stat label="Total billed" value={formatMoney(stats.billed)} />
        <Stat label="Outstanding" value={formatMoney(stats.outstanding)} />
      </div>
      <Filters action="/class-fees">
        <FilterInput name="q" label="Scout" value={sp.q} placeholder="Name, National ID or index" />
        <FilterSelect name="activity" label="Activity" value={sp.activity} options={activities.map((a) => ({ value: a.uuid, label: a.name }))} placeholder="Any activity" />
        <FilterSelect name="section" label="Section" value={sp.section} options={ScoutSection.options()} placeholder="Any section" />
        <FilterSelect name="status" label="Status" value={sp.status} options={statusOptions} placeholder="Any status" />
      </Filters>
      {page.rows.length === 0 ? <Empty message="No class fees match these filters." /> : (
        <>
          <Table headers={['Scout', 'Activity', 'Fee', 'Paid', 'Outstanding', 'Due', 'Status', '']}>
            {page.rows.map(({ fee, student, activity, date }) => (
              <tr key={fee.id}>
                <td data-label="Scout" className="font-medium">{student}</td>
                <td data-label="Activity">{activity} <span className="block text-xs text-gray-400">{formatDate(date)}</span></td>
                <td data-label="Fee">{formatMoney(fee.amount)}</td>
                <td data-label="Paid">{formatMoney(fee.paidAmount)}</td>
                <td data-label="Outstanding">{formatMoney(fee.outstandingAmount)}</td>
                <td data-label="Due">{formatDate(fee.dueDate)}</td>
                <td data-label="Status"><Badge of={FeeStatus} value={fee.status} /></td>
                <td className="text-right">
                  <PayButton payable={{ type: 'class_fee', id: fee.id, uuid: fee.uuid, studentId: fee.studentId, userId: null, amountDue: fee.amount, paidAmount: fee.paidAmount, outstandingAmount: fee.outstandingAmount, status: fee.status, closed: false, description: `Class fee — ${activity}` }} />
                </td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/class-fees" query={{ q: sp.q, activity: sp.activity, section: sp.section, status: sp.status }} />
        </>
      )}
      <PaymentModal />
    </>
  );
}
