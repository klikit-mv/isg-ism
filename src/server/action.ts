import { isRedirectError } from 'next/dist/client/components/redirect-error';
import { isHTTPAccessFallbackError } from 'next/dist/client/components/http-access-fallback/http-access-fallback';
import { NotAccessibleError, ScoutError, ValidationError } from './errors';
import { flash } from './flash';

/** What a form action returns to `useActionState`. */
export interface ActionState {
  error?: string;
  fields?: Record<string, string>;
  /** The submitted values, so the form can be shown again as typed. */
  values?: Record<string, unknown>;
}

const rethrow = (e: unknown) => isRedirectError(e) || isHTTPAccessFallbackError(e);

/** Form with field errors: turn business-rule failures into state. */
export async function handle(fn: () => Promise<void>, values?: Record<string, unknown>): Promise<ActionState | null> {
  try {
    await fn();
    return null;
  } catch (e) {
    if (rethrow(e)) throw e;
    if (e instanceof ValidationError) return { error: e.message, fields: e.fields, values };
    if (e instanceof ScoutError) return { error: e.message, values };
    throw e;
  }
}

/** Button forms: show a failure as a flash message on the same page. */
export async function simple(fn: () => Promise<void>): Promise<void> {
  try {
    await fn();
  } catch (e) {
    if (rethrow(e)) throw e;
    if (e instanceof ValidationError) {
      await flash('error', Object.values(e.fields)[0] ?? e.message);
      return;
    }
    if (e instanceof NotAccessibleError || e instanceof ScoutError) {
      await flash('error', e.message);
      return;
    }
    throw e;
  }
}

/** Snapshot of form values for echoing back (never passwords or PINs). */
export function echo(input: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(input)) {
    if (/pin|password/i.test(k) || v instanceof File) continue;
    out[k] = v;
  }
  return out;
}
