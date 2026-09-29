/**
 * Money is handled as integer cents. Database values are decimal strings
 * such as "12.50", so floating point never touches an amount.
 */
export const toCents = (value: string | number | null | undefined): number => {
  if (value === null || value === undefined || value === '') return 0;
  const text = String(value).trim().replace(/,/g, '');
  const match = /^(-?)(\d*)(?:\.(\d*))?$/.exec(text);
  if (!match) return 0;
  const [, sign, whole = '0', fraction = ''] = match;
  const cents = Number(whole || '0') * 100 + Number((fraction + '00').slice(0, 2));
  // Round half up on the third decimal.
  const rounded = fraction.length > 2 && Number(fraction[2]) >= 5 ? cents + 1 : cents;
  return sign ? -rounded : rounded;
};

export const fromCents = (cents: number): string => {
  const sign = cents < 0 ? '-' : '';
  const abs = Math.abs(Math.round(cents));
  return `${sign}${Math.floor(abs / 100)}.${String(abs % 100).padStart(2, '0')}`;
};

export const normalize = (value: string | number | null | undefined): string => fromCents(toCents(value));
export const add = (...values: (string | number | null | undefined)[]): string =>
  fromCents(values.reduce<number>((sum, v) => sum + toCents(v), 0));
export const sub = (a: string | number | null | undefined, b: string | number | null | undefined): string =>
  fromCents(toCents(a) - toCents(b));
export const mul = (price: string | number | null | undefined, quantity: number): string =>
  fromCents(toCents(price) * quantity);
export const isPositive = (value: string | number | null | undefined): boolean => toCents(value) > 0;
export const compare = (a: string | number | null | undefined, b: string | number | null | undefined): number =>
  Math.sign(toCents(a) - toCents(b));

/** "MVR 1,250.00", with a non-breaking space like the old portal. */
export const formatMoney = (value: string | number | null | undefined, currency = process.env.SCOUT_CURRENCY_SYMBOL ?? 'MVR'): string => {
  const [whole, fraction] = normalize(value).split('.');
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return `${currency} ${grouped}.${fraction}`;
};

export const max = (a: string | number | null | undefined, b: string | number | null | undefined): string => (compare(a, b) >= 0 ? normalize(a) : normalize(b));
export const min = (a: string | number | null | undefined, b: string | number | null | undefined): string => (compare(a, b) <= 0 ? normalize(a) : normalize(b));
