'use server';

import { eq } from 'drizzle-orm';
import { forbidden, notFound, redirect } from 'next/navigation';
import { revalidatePath } from 'next/cache';
import { db, schema } from '@/db';
import { createYear, generateInvoices, setYearStatus } from '@/server/annual-fees';
import { echo, handle, simple, type ActionState } from '@/server/action';
import { flash } from '@/server/flash';
import { unique } from '@/server/rules';
import { requireUser } from '@/server/session';
import { hasPermission, isAdmin } from '@/server/users';
import { RecordStatus, ScoutSection } from '@/lib/enums';
import { normalize } from '@/lib/money';
import { inEnum, parseForm, validate } from '@/lib/validate';

export async function createYearAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await requireUser();
  if (!isAdmin(user)) forbidden();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(input, {
      year: ['required', 'integer', 'min:2000', 'max:2200', unique(schema.annualFeeYears, schema.annualFeeYears.year)],
      amount: ['required', 'numeric', 'min:0', 'max:9999999'],
    });
    await createYear(data.year, normalize(data.amount), user);
    await flash('success', `Fee year ${data.year} was created.`);
    revalidatePath('/annual-fees/years');
  }, echo(input));
}

async function loadYear(value: unknown) {
  const [year] = await db.select().from(schema.annualFeeYears).where(eq(schema.annualFeeYears.year, Number(value))).limit(1);
  if (!year) notFound();
  return year;
}

export async function yearStatusAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  if (!isAdmin(user)) forbidden();
  const year = await loadYear(formData.get('year'));
  await simple(async () => {
    const data = await validate({ status: formData.get('status') }, { status: ['required', inEnum(RecordStatus)] });
    await setYearStatus(year, data.status, user);
    await flash('success', `Fee year ${year.year} is now ${data.status}.`);
    revalidatePath('/annual-fees/years');
  });
}

export async function generateInvoicesAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  if (!hasPermission(user, 'canManageFees')) forbidden();
  const year = await loadYear(formData.get('year'));
  await simple(async () => {
    const input = parseForm(formData);
    const data = await validate(input, {
      people: ['required', 'array', 'min:1'],
      'people.*': ['string', 'in:' + (input.people as string[] | undefined ?? []).filter((k) => /^[su]\d+$/.test(k)).join(',')],
      sections: ['array'],
    }, { 'people.required': 'Choose at least one person to invoice.' });
    const sections = (data.sections ?? {}) as Record<string, string>;
    const people = (data.people as string[]).map((key) => ({
      type: (key[0] === 'u' ? 'Leader' : 'Student') as 'Leader' | 'Student', id: Number(key.slice(1)), section: ScoutSection.is(sections[key]) ? sections[key] : null,
    }));
    const result = await generateInvoices(year, people, user);
    await flash('success', `${result.created} invoice(s) created, ${result.skipped} skipped (already invoiced).`);
    redirect('/annual-fees/years');
  });
}
