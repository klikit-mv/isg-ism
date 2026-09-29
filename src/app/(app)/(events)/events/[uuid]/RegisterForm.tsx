'use client';

import { useState } from 'react';
import { registerAction } from '@/app/actions/events';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Select, Textarea } from '@/components/form/fields';
import { formatMoney, add, mul } from '@/lib/money';

export interface RegisterItem {
  uuid: string;
  name: string;
  description: string | null;
  price: string;
  sizes: { value: string; label: string }[];
  sizeGuide: string | null;
  max: number;
  remaining: number | null;
}

export function RegisterForm({ eventUuid, fee, participants, items }: {
  eventUuid: string;
  fee: string;
  participants: { value: string; label: string }[];
  items: RegisterItem[];
}) {
  const [qty, setQty] = useState<Record<string, number>>({});
  const [size, setSize] = useState<Record<string, string>>({});
  const total = add(fee, ...items.map((i) => mul(i.price, qty[i.uuid] ?? 0)));
  const paymentOptions = [{ value: 'online', label: 'Online transfer (upload proof)' }, { value: 'cash', label: 'Cash to a leader' }];
  return (
    <ActionForm action={registerAction}>
      <input type="hidden" name="event" value={eventUuid} />
      <Select name="student" label="Who is taking part?" options={participants} placeholder="Choose" required />
      {items.length > 0 && (
        <div className="space-y-3">
          <h3 className="text-sm font-semibold">Pre-order items (optional)</h3>
          {items.map((item, idx) => {
            const limit = Math.min(item.max, item.remaining ?? item.max);
            return (
              <div key={item.uuid} className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                <input type="hidden" name={`items[${idx}][item]`} value={item.uuid} />
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="font-medium">{item.name} <span className="text-gold-700 dark:text-gold-400">{formatMoney(item.price)}</span></p>
                    {item.description && <p className="text-xs text-gray-500">{item.description}</p>}
                    {item.remaining !== null && <p className="text-xs text-gray-500">{item.remaining} left</p>}
                  </div>
                  <div className="w-20">
                    <label className="label" htmlFor={`qty_${idx}`}>Qty</label>
                    <input id={`qty_${idx}`} className="input" type="number" min={0} max={limit} name={`items[${idx}][quantity]`} value={qty[item.uuid] ?? 0} disabled={limit < 1}
                      onChange={(e) => setQty({ ...qty, [item.uuid]: Math.max(0, Math.min(limit, Number(e.target.value) || 0)) })} />
                  </div>
                </div>
                {item.sizes.length > 0 && (
                  <div className="mt-2">
                    <label className="label" htmlFor={`size_${idx}`}>Size</label>
                    <select id={`size_${idx}`} name={`items[${idx}][size]`} className="input" value={size[item.uuid] ?? ''} onChange={(e) => setSize({ ...size, [item.uuid]: e.target.value })}>
                      <option value="">Choose a size</option>
                      {item.sizes.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                    {item.sizeGuide && <p className="mt-1 text-xs text-gray-500">{item.sizeGuide}</p>}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
      <Select name="payment_option" label="How will you pay?" options={paymentOptions} defaultValue="online" required />
      <Textarea name="notes" label="Notes (optional)" rows={2} />
      <div className="flex items-center justify-between">
        <p className="text-sm">Total: <strong>{formatMoney(total)}</strong></p>
        <SubmitButton>Register</SubmitButton>
      </div>
    </ActionForm>
  );
}
