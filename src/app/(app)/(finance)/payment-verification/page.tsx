import { forbidden } from 'next/navigation';
import { ActionButton, ConfirmButton } from '@/components/ConfirmButton';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { formatDateTime } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import { approvePaymentAction, rejectPaymentAction } from '@/app/actions/payments';
import { verificationQueue } from '@/server/finance-queries';
import { PAYABLE_LABELS, isPayableType } from '@/server/payables';
import { canRecordCash } from '@/server/payments';
import { requireUser } from '@/server/session';

export const metadata = { title: 'Payment verification' };

export default async function VerificationPage({ searchParams }: { searchParams: Promise<{ page?: string }> }) {
  const user = await requireUser();
  if (!canRecordCash(user)) forbidden();
  const page = await verificationQueue(currentPage((await searchParams).page));
  return (
    <>
      <PageHeader title="Payment verification" description="Online payments waiting for a check, oldest first." />
      {page.rows.length === 0 ? <Empty message="Nothing is waiting for verification." /> : (
        <div className="space-y-4">
          {page.rows.map(({ payment, student, submitter, proof, payable }) => (
            <div key={payment.id} className="card flex flex-col gap-4 md:flex-row">
              <div className="md:w-48">
                {proof?.mimeType?.startsWith('image/') ? (
                  <a href={`/payments/${payment.uuid}/proof`} target="_blank" rel="noopener"><img src={`/payments/${payment.uuid}/proof`} alt="Payment proof" className="max-h-48 w-full rounded-lg border object-contain dark:border-gray-700" /></a>
                ) : proof ? (
                  <a href={`/payments/${payment.uuid}/proof`} target="_blank" rel="noopener" className="btn-secondary w-full">Open proof (PDF)</a>
                ) : <span className="text-sm text-gray-500">No proof</span>}
              </div>
              <div className="flex-1 space-y-1 text-sm">
                <div className="text-lg font-semibold">{formatMoney(payment.amount)}</div>
                <div>{isPayableType(payment.payableType) ? PAYABLE_LABELS[payment.payableType] : payment.payableType} · {payable?.description}</div>
                <div>Scout: {student ?? '—'}</div>
                <div className="text-gray-500">Submitted by {submitter} on {formatDateTime(payment.submittedAt)}</div>
                <div className="text-gray-500">Outstanding on this record: {formatMoney(payable?.outstandingAmount)}</div>
              </div>
              <div className="flex flex-row gap-2 md:flex-col">
                <ActionButton action={approvePaymentAction} label="Approve" variant="accent" size="md" fields={{ uuid: payment.uuid }} />
                <ConfirmButton action={rejectPaymentAction} fields={{ uuid: payment.uuid }} label="Reject" size="md" title="Reject payment" message="The submitter will see this reason." confirm="Reject payment">
                  <div>
                    <label className="label" htmlFor={`reason-${payment.id}`}>Reason</label>
                    <input id={`reason-${payment.id}`} name="reason" required maxLength={255} className="input" />
                  </div>
                </ConfirmButton>
              </div>
            </div>
          ))}
        </div>
      )}
      <Pagination page={page.page} pages={page.pages} path="/payment-verification" query={{}} />
    </>
  );
}
