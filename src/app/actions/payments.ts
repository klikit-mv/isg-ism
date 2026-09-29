'use server';

import { eq } from 'drizzle-orm';
import { forbidden, notFound } from 'next/navigation';
import { revalidatePath } from 'next/cache';
import { db, schema } from '@/db';
import { echo, handle, simple, type ActionState } from '@/server/action';
import { flash } from '@/server/flash';
import { approvePayment, canRecordCash, rejectPayment, submitPayment } from '@/server/payments';
import { isPayableType, loadPayable, PAYABLE_TYPES } from '@/server/payables';
import { requireUser } from '@/server/session';
import { ScoutError } from '@/server/errors';
import { PaymentMethod } from '@/lib/enums';
import { inEnum, parseForm, validate } from '@/lib/validate';
import { proofMaxKb } from '@/server/settings';
import { isUpload } from '@/server/uploads';

export async function submitPaymentAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await requireUser();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(input, {
      payable_type: ['required', 'in:' + PAYABLE_TYPES.join(',')],
      payable_id: ['required', 'uuid'],
      amount: ['required', 'numeric', 'min:0.01', 'max:9999999'],
      method: ['required', inEnum(PaymentMethod)],
    });
    if (!isPayableType(data.payable_type)) throw new ScoutError('Unknown payment type.');
    const payable = await loadPayable(data.payable_type, { uuid: data.payable_id });
    if (!payable) notFound();
    const proof = isUpload(input.proof) ? input.proof : null;
    if (proof && proof.size > (await proofMaxKb()) * 1024) throw new ScoutError('The proof file is too large.', 'proof');
    const payment = await submitPayment(payable, user, String(data.amount), data.method, proof);
    await flash('success', payment.status === 'Paid' ? 'The cash payment was recorded.' : 'Thank you. Your payment was sent for verification.');
    revalidatePath('/', 'layout');
  }, echo(input));
}

async function decision(formData: FormData) {
  const user = await requireUser();
  if (!canRecordCash(user)) forbidden();
  const [payment] = await db.select().from(schema.payments).where(eq(schema.payments.uuid, String(formData.get('uuid') ?? ''))).limit(1);
  if (!payment) notFound();
  return { user, payment };
}

export async function approvePaymentAction(formData: FormData): Promise<void> {
  const { user, payment } = await decision(formData);
  await simple(async () => {
    await approvePayment(payment.id, user);
    await flash('success', 'The payment was approved.');
    revalidatePath('/payment-verification');
  });
}

export async function rejectPaymentAction(formData: FormData): Promise<void> {
  const { user, payment } = await decision(formData);
  await simple(async () => {
    await rejectPayment(payment.id, user, String(formData.get('reason') ?? ''));
    await flash('success', 'The payment was rejected.');
    revalidatePath('/payment-verification');
  });
}
