import { canRecordCash } from '@/server/payments';
import { getSetting, proofMaxKb, proofMimes } from '@/server/settings';
import { requireUser } from '@/server/session';
import { PayModal } from './PayModal';

/** Server wrapper: loads the bank details and limits for the payment dialog. */
export async function PaymentModal() {
  const user = await requireUser();
  const [mimes, maxKb, bank, accountName, accountNumber, instructions] = await Promise.all([
    proofMimes(), proofMaxKb(), getSetting('bank_name'), getSetting('account_name'), getSetting('account_number'), getSetting('payment_instructions'),
  ]);
  return <PayModal canCash={canRecordCash(user)} mimes={mimes} maxMb={Math.round((maxKb / 1024) * 10) / 10} bank={{ bank, accountName, accountNumber, instructions }} />;
}
