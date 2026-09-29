import { config } from './config';

const pad = (n: number) => String(n).padStart(2, '0');

/** Wall-clock parts of an instant in the organisation timezone. */
function parts(value: Date, timeZone = config.timezone) {
  const f = new Intl.DateTimeFormat('en-GB', {
    timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  });
  const out: Record<string, string> = {};
  for (const p of f.formatToParts(value)) out[p.type] = p.value;
  return { y: out.year, m: out.month, d: out.day, h: out.hour, i: out.minute };
}

const toDate = (value: Date | string | null | undefined): Date | null => {
  if (value === null || value === undefined || value === '') return null;
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value;
  // Plain dates ("2026-03-01") are calendar days, not instants.
  const d = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00Z`) : new Date(value);
  return Number.isNaN(d.getTime()) ? null : d;
};

/** dd.mm.yyyy. Calendar dates are shown as they are, instants in the organisation timezone. */
export function formatDate(value: Date | string | null | undefined): string {
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    const [y, m, d] = value.split('-');
    return `${d}.${m}.${y}`;
  }
  const date = toDate(value);
  if (!date) return '';
  const p = parts(date);
  return `${p.d}.${p.m}.${p.y}`;
}

/** dd.mm.yyyy hh:mm in the organisation timezone. */
export function formatDateTime(value: Date | string | null | undefined): string {
  const date = toDate(value);
  if (!date) return '';
  const p = parts(date);
  return `${p.d}.${p.m}.${p.y} ${p.h}:${p.i}`;
}

/** "27 September 2026", for certificates. */
export function formatLongDate(value: Date | string | null | undefined): string {
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    const [y, m, d] = value.split('-').map(Number);
    return `${d} ${new Date(Date.UTC(y, m - 1, 1)).toLocaleString('en-GB', { month: 'long', timeZone: 'UTC' })} ${y}`;
  }
  const date = toDate(value);
  if (!date) return '';
  return new Intl.DateTimeFormat('en-GB', { timeZone: config.timezone, day: 'numeric', month: 'long', year: 'numeric' }).format(date);
}

/** Today as yyyy-mm-dd in the organisation timezone. */
export function todayLocal(now = new Date()): string {
  const p = parts(now);
  return `${p.y}-${p.m}-${p.d}`;
}

/** Value for <input type="datetime-local"> in the organisation timezone. */
export function toLocalInput(value: Date | null | undefined): string {
  if (!value) return '';
  const p = parts(value);
  return `${p.y}-${p.m}-${p.d}T${p.h}:${p.i}`;
}

/** Parse a "yyyy-mm-ddThh:mm" form value entered in the organisation timezone. */
export function fromLocalInput(value: string | null | undefined): Date | null {
  if (!value) return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec(value);
  if (!m) return null;
  const [, y, mo, d, h, i] = m.map(Number) as unknown as number[];
  const asUtc = Date.UTC(y, mo - 1, d, h, i);
  // Find the timezone offset at that wall-clock time.
  const guess = new Date(asUtc);
  const p = parts(guess);
  const seen = Date.UTC(Number(p.y), Number(p.m) - 1, Number(p.d), Number(p.h), Number(p.i));
  return new Date(asUtc - (seen - asUtc));
}

/** Parse dd.mm.yyyy, d/m/yyyy or yyyy-mm-dd into yyyy-mm-dd. */
export function parseFlexibleDate(value: string | null | undefined): string | null {
  const text = (value ?? '').trim();
  if (!text) return null;
  let y: number, m: number, d: number;
  let match = /^(\d{4})-(\d{1,2})-(\d{1,2})/.exec(text);
  if (match) {
    [y, m, d] = [Number(match[1]), Number(match[2]), Number(match[3])];
  } else if ((match = /^(\d{1,2})[./-](\d{1,2})[./-](\d{4})$/.exec(text))) {
    [d, m, y] = [Number(match[1]), Number(match[2]), Number(match[3])];
  } else {
    return null;
  }
  const check = new Date(Date.UTC(y, m - 1, d));
  if (check.getUTCFullYear() !== y || check.getUTCMonth() !== m - 1 || check.getUTCDate() !== d) return null;
  return `${y}-${pad(m)}-${pad(d)}`;
}

/** Midnight at the start of today in the organisation timezone, as an instant. */
export function startOfToday(now = new Date()): Date {
  return fromLocalInput(`${todayLocal(now)}T00:00`) ?? now;
}
