'use client';

import { useEffect, useState } from 'react';
import { submitPaymentAction } from '@/app/actions/payments';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Modal, CancelButton, closeModal, openModal } from '@/components/Modal';

export interface PayTarget {
  type: string;
  id: string;
  amount: string;
  description: string;
}

/** Ask the payment modal on this page to open for a payable. */
export const requestPayment = (target: PayTarget) => window.dispatchEvent(new CustomEvent('pay', { detail: target }));

export function PayButton({ target }: { target: PayTarget }) {
  return <button type="button" className="btn-accent btn-sm" onClick={() => requestPayment(target)}>Pay</button>;
}

/** Opens the payment modal as soon as the page loads (after registering, for example). */
export function OpenPaymentOnLoad({ target }: { target: PayTarget }) {
  useEffect(() => {
    // Wait one tick so the payment dialog on the page has registered its listener.
    const timer = setTimeout(() => requestPayment(target), 0);
    return () => clearTimeout(timer);
  }, [target]);
  return null;
}

export interface BankInfo {
  bank: string | null;
  accountName: string | null;
  accountNumber: string | null;
  instructions: string | null;
}

/** One payment dialog per page; rows open it with `requestPayment`. */
export function PayModal({ canCash, mimes, maxMb, bank }: { canCash: boolean; mimes: string[]; maxMb: number; bank: BankInfo }) {
  const [target, setTarget] = useState<PayTarget | null>(null);
  const [method, setMethod] = useState('online');
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    const on = (e: Event) => {
      setTarget((e as CustomEvent<PayTarget>).detail);
      setMethod('online');
      openModal('pay');
    };
    window.addEventListener('pay', on);
    return () => window.removeEventListener('pay', on);
  }, []);

  return (
    <>
      <Modal name="pay" title="Make a payment" maxWidth="lg">
        {target && (
          <ActionForm action={submitPaymentAction} closeModalOnSuccess="pay" encType="multipart/form-data">
            <input type="hidden" name="payable_type" value={target.type} />
            <input type="hidden" name="payable_id" value={target.id} />
            <p className="text-sm text-gray-600 dark:text-gray-300">{target.description}</p>
            <div>
              <label className="label" htmlFor="pay_amount">Amount</label>
              <input id="pay_amount" name="amount" type="number" step="0.01" min="0.01" className="input" defaultValue={target.amount} required />
            </div>
            <div>
              <label className="label" htmlFor="pay_method">Method</label>
              <select id="pay_method" name="method" className="input" value={method} onChange={(e) => setMethod(e.target.value)}>
                <option value="online">Online transfer (upload proof)</option>
                {canCash && <option value="cash">Cash received by staff</option>}
              </select>
            </div>
            {method === 'online' && (
              <div className="space-y-2">
                <button type="button" className="link text-sm" onClick={() => openModal('bank')}>Show bank details</button>
                <div>
                  <label className="label" htmlFor="pay_proof">Proof of payment</label>
                  <input id="pay_proof" name="proof" type="file" accept={mimes.map((m) => `.${m}`).join(',')} className="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-50 file:px-3 file:py-2 file:text-navy-700 dark:text-gray-300 dark:file:bg-navy-900 dark:file:text-navy-200" />
                  <p className="mt-1 text-xs text-gray-500">{mimes.join(', ').toUpperCase()} up to {maxMb} MB.</p>
                </div>
              </div>
            )}
            <div className="flex justify-end gap-2">
              <CancelButton name="pay" />
              <SubmitButton pendingText="Sending…">Submit payment</SubmitButton>
            </div>
          </ActionForm>
        )}
      </Modal>
      <Modal name="bank" title="Bank details" maxWidth="sm">
        <dl className="space-y-2 text-sm">
          <div><dt className="text-gray-500">Bank</dt><dd>{bank.bank || 'Not set'}</dd></div>
          <div><dt className="text-gray-500">Account name</dt><dd>{bank.accountName || 'Not set'}</dd></div>
          <div>
            <dt className="text-gray-500">Account number</dt>
            <dd className="flex items-center gap-2">
              <span className="font-mono">{bank.accountNumber || 'Not set'}</span>
              {bank.accountNumber && (
                <button type="button" className="btn-secondary btn-sm" onClick={async () => { try { await navigator.clipboard.writeText(bank.accountNumber!); setCopied(true); setTimeout(() => setCopied(false), 1500); } catch { window.prompt('Copy this:', bank.accountNumber!); } }}>{copied ? 'Copied' : 'Copy'}</button>
              )}
            </dd>
          </div>
          {bank.instructions && <div className="whitespace-pre-line pt-2 text-gray-600 dark:text-gray-300">{bank.instructions}</div>}
        </dl>
        <div className="mt-4 flex justify-end"><button type="button" className="btn-secondary" onClick={() => closeModal('bank')}>Close</button></div>
      </Modal>
    </>
  );
}
