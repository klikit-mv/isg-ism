'use server';

import { notFound } from 'next/navigation';
import { findActivityByUuid } from '@/server/activities';
import { markAttendance, type Mark } from '@/server/attendance';
import { afterAttendanceSave } from '@/server/certificate-hooks';
import { updateActivityFee } from '@/server/class-fees';
import { ScoutError, ValidationError } from '@/server/errors';
import { markRovers } from '@/server/rover-attendance';
import { requireStaff } from '@/server/session';
import { isNumeric } from '@/lib/numbers';

export interface RegisterResult {
  ok: boolean;
  message: string;
}

async function load(uuid: string) {
  const activity = await findActivityByUuid(uuid);
  if (!activity) notFound();
  return { actor: await requireStaff(), activity };
}

const fail = (e: unknown): RegisterResult => {
  if (e instanceof ValidationError) return { ok: false, message: Object.values(e.fields)[0] ?? e.message };
  if (e instanceof ScoutError) return { ok: false, message: e.message };
  throw e;
};

/** Save the attendance register. `rows` maps student id to the chosen values. */
export async function saveAttendanceAction(uuid: string, rows: Record<string, { status: string; remarks: string; payment: string; other: string }>): Promise<RegisterResult> {
  const { actor, activity } = await load(uuid);
  const marks: Record<string, Mark> = {};
  for (const [id, row] of Object.entries(rows)) {
    if (!row.status) continue;
    let payment: string | null = null;
    if (activity.chargeFee && row.payment !== '') {
      payment = row.payment === 'other' ? row.other : row.payment;
      if (row.payment === 'other' && (!isNumeric(payment) || Number(payment) < 0)) return { ok: false, message: 'Enter a valid “Other” amount for every scout marked as Other.' };
    }
    marks[id] = { status: row.status, remarks: row.remarks, payment };
  }
  try {
    const result = await markAttendance(activity, actor, marks);
    const message = `Saved ${result.marked} mark(s).`;
    const extra = await afterAttendanceSave(activity, actor);
    if (extra) return { ok: !extra.error, message: [message, extra.message, extra.error].filter(Boolean).join(' ') };
    return { ok: true, message };
  } catch (e) {
    return fail(e);
  }
}

export async function updateFeeAction(uuid: string, amount: string): Promise<RegisterResult> {
  const { actor, activity } = await load(uuid);
  try {
    if (!isNumeric(amount) || Number(amount) < 0) return { ok: false, message: 'The fee due must be a number, zero or more.' };
    const count = await updateActivityFee(activity, amount, actor);
    return { ok: true, message: `Fee updated. ${count} class fee(s) re-priced.` };
  } catch (e) {
    return fail(e);
  }
}

export async function saveRoverAction(uuid: string, marks: Record<string, string>): Promise<RegisterResult> {
  const { actor, activity } = await load(uuid);
  try {
    const clean = Object.fromEntries(Object.entries(marks).filter(([, v]) => v));
    const result = await markRovers(activity, actor, clean);
    return { ok: true, message: `Saved ${result.marked} Rover mark(s).` };
  } catch (e) {
    return fail(e);
  }
}
