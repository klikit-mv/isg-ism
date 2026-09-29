import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { createYear, generateInvoices, setYearStatus } from '@/server/annual-fees';
import { loadPayableOrThrow } from '@/server/payables';
import { submitPayment } from '@/server/payments';
import { makeAdmin, makeLeader, makeStudent } from '../factories';

describe('annual fees', () => {
  it('generating invoices is idempotent and covers scouts and leaders', async () => {
    const admin = await makeAdmin();
    const scout = await makeStudent();
    const leader = await makeLeader();
    const year = await createYear(2030, '120.00', admin);
    const people = [{ type: 'Student' as const, id: scout.id }, { type: 'Leader' as const, id: leader.id }];

    expect(await generateInvoices(year, people, admin)).toEqual({ created: 2, skipped: 0 });
    expect(await generateInvoices(year, people, admin)).toEqual({ created: 0, skipped: 2 });
    const fees = await db.select().from(schema.annualFees);
    expect(fees).toHaveLength(2);
    expect(fees.find((f) => f.userId === leader.id)?.personType).toBe('Leader');
    expect(fees.find((f) => f.studentId === scout.id)?.section).toBe('Scout');
  });

  it('inactive years cannot be invoiced', async () => {
    const admin = await makeAdmin();
    const scout = await makeStudent();
    const year = await createYear(2031, '100.00', admin);
    await setYearStatus(year, 'Inactive', admin);
    const [fresh] = await db.select().from(schema.annualFeeYears).where(eq(schema.annualFeeYears.id, year.id));
    await expect(generateInvoices(fresh, [{ type: 'Student', id: scout.id }], admin)).rejects.toThrow('Only Active annual fee years');
  });

  it('a leader pays their own annual fee in cash by an admin and it is settled', async () => {
    const admin = await makeAdmin();
    const leader = await makeLeader();
    const year = await createYear(2032, '80.00', admin);
    await generateInvoices(year, [{ type: 'Leader', id: leader.id }], admin);
    const [fee] = await db.select().from(schema.annualFees);
    const payable = await loadPayableOrThrow('annual_fee', { id: fee.id });
    await submitPayment(payable, admin, '80.00', 'cash');
    const settled = await loadPayableOrThrow('annual_fee', { id: fee.id });
    expect(settled.status).toBe('Paid');
    expect(settled.outstandingAmount).toBe('0.00');
  });
});
