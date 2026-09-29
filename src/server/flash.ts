import { cookies } from 'next/headers';

export type FlashKind = 'success' | 'error' | 'warning';
export interface Flash {
  kind: FlashKind;
  message: string;
}

/** One-shot message shown on the next page; the browser clears the cookie. */
export async function flash(kind: FlashKind, message: string): Promise<void> {
  (await cookies()).set('flash', JSON.stringify({ kind, message } satisfies Flash), { path: '/', maxAge: 60, sameSite: 'lax' });
}

export async function readFlash(): Promise<Flash | null> {
  const raw = (await cookies()).get('flash')?.value;
  if (!raw) return null;
  try {
    const parsed = JSON.parse(raw) as Flash;
    return typeof parsed.message === 'string' ? parsed : null;
  } catch {
    return null;
  }
}
