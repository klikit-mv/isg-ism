import { Badge } from '@/components/Badge';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { PaymentMethod, PaymentStatus } from '@/lib/enums';
import { formatDateTime } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import { listPayments } from '@/server/finance-queries';
import { PAYABLE_LABELS, isPayableType } from '@/server/payables';
import { SOURCE_ROSTER } from '@/server/payments';
import { requireUser } from '@/server/session';

export const metadata = { title: 'Payments' };

export default async function PaymentsPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  const sp = await searchParams;
  const page = await listPayments(user, { q: sp.q, status: sp.status, method: sp.method, page: currentPage(sp.page) });
  return (
    <>
      <PageHeader title="Payments" description="Online and cash payments." />
      <Filters action="/payments">
        <FilterInput name="q" label="Search" value={sp.q} placeholder="Payment id, scout or National ID" />
        <FilterSelect name="status" label="Status" value={sp.status} options={PaymentStatus.options()} placeholder="Any status" />
        <FilterSelect name="method" label="Method" value={sp.method} options={PaymentMethod.options()} placeholder="Any method" />
      </Filters>
      {page.rows.length === 0 ? <Empty message="No payments yet." /> : (
        <>
          <Table headers={['Submitted', 'For', 'Scout', 'Amount', 'Method', 'Status', 'Verified by', '']}>
            {page.rows.map(({ payment, student, submitter, verifier, proofUuid }) => (
              <tr key={payment.id}>
                <td data-label="Submitted" className="whitespace-nowrap">{formatDateTime(payment.submittedAt)}</td>
                <td data-label="For">{isPayableType(payment.payableType) ? PAYABLE_LABELS[payment.payableType] : payment.payableType}{payment.source === SOURCE_ROSTER && <span className="text-xs text-gray-400"> (roster)</span>}</td>
                <td data-label="Scout">{student ?? submitter}</td>
                <td data-label="Amount">{formatMoney(payment.amount)}</td>
                <td data-label="Method">{PaymentMethod.label(payment.method)}</td>
                <td data-label="Status">
                  <Badge of={PaymentStatus} value={payment.status} />
                  {payment.rejectionReason && <div className="mt-1 text-xs text-rose-600">{payment.rejectionReason}</div>}
                </td>
                <td data-label="Verified by">{verifier ?? '—'}</td>
                <td className="text-right">{proofUuid && <a href={`/payments/${payment.uuid}/proof`} className="link" target="_blank" rel="noopener">Proof</a>}</td>
              </tr>
            ))}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/payments" query={{ q: sp.q, status: sp.status, method: sp.method }} />
        </>
      )}
    </>
  );
}
