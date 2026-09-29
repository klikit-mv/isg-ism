import { ValidationError } from '@/server/errors';
import { parseFlexibleDate } from './dates';

/**
 * A small validator with Laravel-style rules so the old validation can be
 * ported one to one: 'required', 'nullable', 'string', 'max:255', 'email',
 * 'date', 'before:today', 'confirmed', 'in:a,b', 'uuid', 'array' ... or an
 * (async) function returning an error message or null.
 */
export type Ctx = { input: Record<string, unknown>; field: string; label: string; path: string };
export type Rule = string | ((value: any, ctx: Ctx) => string | null | Promise<string | null>);
export type Rules = Record<string, Rule[]>;

const label = (path: string) => path.split('.').filter((p) => p !== '*' && !/^\d+$/.test(p)).pop()!.replace(/_/g, ' ');

const isMissing = (v: unknown) => v === undefined || v === null || v === '' || (Array.isArray(v) && v.length === 0);

/** Trim strings and turn empty strings into null, like Laravel's middleware. */
export function clean<T>(value: T): T {
  if (typeof value === 'string') {
    const t = value.trim();
    return (t === '' ? null : t) as T;
  }
  if (Array.isArray(value)) return value.map(clean) as T;
  if (value && typeof value === 'object' && !(value instanceof Date) && !(value instanceof File)) {
    return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, clean(v)])) as T;
  }
  return value;
}

/** Turn FormData keys such as a[b][c] and a[] into nested objects and arrays. */
export function parseForm(formData: FormData): Record<string, unknown> {
  const root: Record<string, any> = {};
  for (const [rawKey, value] of formData.entries()) {
    if (rawKey.startsWith('$ACTION')) continue;
    const keys = rawKey.replace(/\]/g, '').split('[');
    let node: any = root;
    keys.forEach((key, i) => {
      const last = i === keys.length - 1;
      const next = keys[i + 1];
      if (key === '') {
        // a[] push
        if (last) node.push(value);
        return;
      }
      if (last) {
        node[key] = value;
      } else {
        node[key] ??= next === '' || /^\d+$/.test(next) ? [] : {};
        node = node[key];
      }
    });
  }
  return root;
}

const get = (obj: any, path: string[]): unknown => path.reduce((o, k) => (o == null ? undefined : o[k]), obj);
const set = (obj: any, path: string[], value: unknown) => {
  let node = obj;
  path.forEach((k, i) => {
    if (i === path.length - 1) node[k] = value;
    else node = node[k] ??= /^\d+$/.test(path[i + 1]) ? [] : {};
  });
};

/** Expand "items.*.item" into concrete paths using the input. */
function expand(pattern: string, input: any): string[][] {
  let paths: string[][] = [[]];
  for (const part of pattern.split('.')) {
    const next: string[][] = [];
    for (const p of paths) {
      if (part === '*') {
        const arr = get(input, p);
        if (Array.isArray(arr)) arr.forEach((_, i) => next.push([...p, String(i)]));
        else if (arr && typeof arr === 'object') Object.keys(arr).forEach((k) => next.push([...p, k]));
      } else next.push([...p, part]);
    }
    paths = next;
  }
  return paths;
}

const BOOL_TRUE = new Set(['1', 'true', 'on', 'yes', 1, true]);
const BOOL_FALSE = new Set(['0', 'false', 'off', 'no', 0, false]);

async function check(rule: Rule, value: any, ctx: Ctx, rules: Rule[], isNumeric: boolean): Promise<{ error?: string; value?: unknown }> {
  const name = ctx.label;
  if (typeof rule === 'function') {
    const error = await rule(value, ctx);
    return error ? { error } : {};
  }
  const [kind, arg = ''] = [rule.split(':')[0], rule.includes(':') ? rule.slice(rule.indexOf(':') + 1) : ''];
  switch (kind) {
    case 'string':
      return typeof value === 'string' ? {} : { error: `The ${name} field must be a string.` };
    case 'email':
      return typeof value === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? {} : { error: `The ${name} field must be a valid email address.` };
    case 'url':
      try { new URL(String(value)); return {}; } catch { return { error: `The ${name} field must be a valid URL.` }; }
    case 'uuid':
      return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(value)) ? {} : { error: `The ${name} field must be a valid UUID.` };
    case 'numeric': {
      const ok = typeof value === 'number' || (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value));
      return ok ? {} : { error: `The ${name} field must be a number.` };
    }
    case 'integer': {
      const ok = Number.isInteger(value) || (typeof value === 'string' && /^-?\d+$/.test(value));
      return ok ? { value: Number(value) } : { error: `The ${name} field must be an integer.` };
    }
    case 'boolean':
      if (BOOL_TRUE.has(value)) return { value: true };
      if (BOOL_FALSE.has(value)) return { value: false };
      return { error: `The ${name} field must be true or false.` };
    case 'array':
      return Array.isArray(value) ? {} : { error: `The ${name} field must be an array.` };
    case 'date': {
      const d = parseFlexibleDate(String(value));
      return d ? { value: d } : { error: `The ${name} field must be a valid date.` };
    }
    case 'datetime':
      return /^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/.test(String(value)) ? {} : { error: `The ${name} field must be a valid date and time.` };
    case 'before': {
      const d = parseFlexibleDate(String(value));
      const limit = arg === 'today' ? new Date().toISOString().slice(0, 10) : arg;
      return d && d < limit ? {} : { error: `The ${name} field must be a date before ${arg}.` };
    }
    case 'before_or_equal':
    case 'after_or_equal': {
      const other = String((ctx.input as any)[arg] ?? '');
      if (!other) return {};
      const a = String(value).slice(0, 16);
      const b = other.slice(0, 16);
      const ok = kind === 'before_or_equal' ? a <= b : a >= b;
      return ok ? {} : { error: `The ${name} field must be a date ${kind === 'before_or_equal' ? 'before or equal to' : 'after or equal to'} ${arg.replace(/_/g, ' ')}.` };
    }
    case 'confirmed':
      return (ctx.input as any)[`${ctx.field}_confirmation`] === value ? {} : { error: `The ${name} field confirmation does not match.` };
    case 'in': {
      const allowed = arg.split(',');
      return allowed.includes(String(value)) ? {} : { error: `The selected ${name} is invalid.` };
    }
    case 'min':
    case 'max': {
      const limit = Number(arg);
      const size = Array.isArray(value) ? value.length : isNumeric ? Number(value) : String(value).length;
      const ok = kind === 'min' ? size >= limit : size <= limit;
      if (ok) return {};
      const unit = Array.isArray(value) ? ' items' : isNumeric ? '' : ' characters';
      return { error: `The ${name} field must be ${kind === 'min' ? 'at least' : 'no more than'} ${limit}${unit}.` };
    }
    default:
      return {};
  }
}

/**
 * Validate `input` against `rules`. Returns the cleaned values for the fields
 * named in the rules, or throws a ValidationError with a message per field.
 */
export async function validate(
  rawInput: Record<string, unknown>,
  rules: Rules,
  messages: Record<string, string> = {},
): Promise<Record<string, any>> {
  const input = clean(rawInput) as Record<string, unknown>;
  const errors: Record<string, string> = {};
  const out: Record<string, any> = {};

  for (const [pattern, ruleList] of Object.entries(rules)) {
    const paths = pattern.includes('*') ? expand(pattern, input) : [pattern.split('.')];
    const nullable = ruleList.includes('nullable') || ruleList.includes('sometimes');
    const isNumeric = ruleList.includes('numeric') || ruleList.includes('integer');

    for (const path of paths) {
      const key = path.join('.');
      const value = get(input, path);
      const ctx: Ctx = { input, field: path[path.length - 1], label: label(key), path: key };
      const say = (rule: string, fallback: string) => messages[`${pattern}.${rule}`] ?? messages[`${key}.${rule}`] ?? fallback;

      if (isMissing(value)) {
        if (ruleList.includes('required')) errors[key] ??= say('required', `The ${ctx.label} field is required.`);
        else if (ruleList.includes('nullable') || value === null) set(out, path, null);
        continue;
      }

      let current: unknown = value;
      for (const rule of ruleList) {
        if (rule === 'required' || rule === 'nullable' || rule === 'sometimes') continue;
        const ruleName = typeof rule === 'string' ? rule.split(':')[0] : 'custom';
        const result = await check(rule, current, ctx, ruleList, isNumeric);
        if (result.error) {
          errors[key] ??= say(ruleName, result.error);
          break;
        }
        if ('value' in result) current = result.value;
      }
      if (!errors[key]) set(out, path, current);
    }
    void nullable;
  }

  if (Object.keys(errors).length) throw new ValidationError(errors);
  return out;
}

/** Rule: one of the enum values. */
export const inEnum = (e: { values: readonly string[]; label(v: string): string }, message?: string): Rule =>
  (value, ctx) => (e.values.includes(String(value)) ? null : message ?? `The selected ${ctx.label} is invalid.`);
