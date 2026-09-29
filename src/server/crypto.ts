import { createCipheriv, createDecipheriv, createHmac, randomBytes, timingSafeEqual } from 'node:crypto';

/**
 * Laravel-compatible string encryption (AES-256-CBC + HMAC), keyed by APP_KEY,
 * so secrets stored by the old portal can still be read.
 */
function key(): Buffer {
  const raw = process.env.APP_KEY ?? '';
  if (!raw) throw new Error('APP_KEY is not set.');
  const buf = raw.startsWith('base64:') ? Buffer.from(raw.slice(7), 'base64') : Buffer.from(raw);
  if (buf.length !== 32) throw new Error('APP_KEY must be 32 bytes (use: openssl rand -base64 32, prefixed with base64:).');
  return buf;
}

const sign = (iv: string, value: string) => createHmac('sha256', key()).update(iv + value).digest('hex');

export function encryptString(plain: string): string {
  const iv = randomBytes(16);
  const cipher = createCipheriv('aes-256-cbc', key(), iv);
  const value = Buffer.concat([cipher.update(plain, 'utf8'), cipher.final()]).toString('base64');
  const ivB64 = iv.toString('base64');
  return Buffer.from(JSON.stringify({ iv: ivB64, value, mac: sign(ivB64, value), tag: '' })).toString('base64');
}

/** Returns null when the value is not valid for this key. */
export function decryptString(payload: string): string | null {
  try {
    const json = JSON.parse(Buffer.from(payload, 'base64').toString('utf8')) as { iv: string; value: string; mac: string };
    const expected = Buffer.from(sign(json.iv, json.value));
    const given = Buffer.from(json.mac);
    if (expected.length !== given.length || !timingSafeEqual(expected, given)) return null;
    const decipher = createDecipheriv('aes-256-cbc', key(), Buffer.from(json.iv, 'base64'));
    return Buffer.concat([decipher.update(Buffer.from(json.value, 'base64')), decipher.final()]).toString('utf8');
  } catch {
    return null;
  }
}
