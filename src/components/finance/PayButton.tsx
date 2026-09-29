import { formatMoney } from '@/lib/money';
import type { PayableInfo } from '@/server/payables';
import { PayButton as ClientPayButton } from './PayModal';

/** A Pay button for a payable that still has something to pay. */
export function PayButton({ payable }: { payable: PayableInfo }) {
  if (payable.closed || payable.status === 'Paid' || payable.status === 'Void') return null;
  return (
    <ClientPayButton target={{ type: payable.type, id: payable.uuid, amount: payable.outstandingAmount, description: `${payable.description} — outstanding ${formatMoney(payable.outstandingAmount)}` }} />
  );
}
