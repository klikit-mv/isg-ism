import { randomBytes } from 'node:crypto';
import { eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import { ValidationError } from './errors';
import { get, put, remove, sniffImage } from './storage';
import type { AuthUser } from './users';

/** Leader and admin signature images used when signing certificates (private disk). */
export async function storeSignature(user: Pick<AuthUser, 'id' | 'uuid' | 'signaturePath'>, file: File): Promise<string> {
  if (file.size > 2048 * 1024) throw new ValidationError({ signature: 'The signature must not be greater than 2048 kilobytes.' });
  const bytes = Buffer.from(await file.arrayBuffer());
  const kind = sniffImage(bytes);
  if (kind !== 'png' && kind !== 'jpg') throw new ValidationError({ signature: 'The signature must be a PNG or JPEG image.' });
  const path = `${user.uuid}-${randomBytes(3).toString('hex')}.${kind}`;
  await put('signatures', path, bytes);
  await db.update(schema.users).set({ signaturePath: path, updatedAt: new Date() }).where(eq(schema.users.id, user.id));
  if (user.signaturePath && user.signaturePath !== path) await remove('signatures', user.signaturePath);
  return path;
}

/** The signature as a data URI for PDFs, or null. */
export async function signatureDataUri(user: Pick<AuthUser, 'signaturePath'> | null): Promise<string | null> {
  if (!user?.signaturePath) return null;
  const bytes = await get('signatures', user.signaturePath);
  if (!bytes) return null;
  const mime = user.signaturePath.toLowerCase().endsWith('.png') ? 'image/png' : 'image/jpeg';
  return `data:${mime};base64,${bytes.toString('base64')}`;
}

/** A drawn-name signature for signers with no uploaded image. */
export function signatureFromName(name: string): string {
  const safe = name.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!);
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="360" height="90"><text x="10" y="60" font-family="DejaVu Serif, serif" font-style="italic" font-size="36" fill="#1e1b4b">${safe}</text></svg>`;
  return `data:image/svg+xml;base64,${Buffer.from(svg).toString('base64')}`;
}
