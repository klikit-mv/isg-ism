import { promises as fs } from 'node:fs';
import path from 'node:path';
import { config } from '@/lib/config';

/**
 * File storage on disk. Two areas, like the old portal:
 * "public" (logo, photos, badge images) and "local" (payment proofs, certificates, imports).
 * Files are only ever served through the app, which checks who is asking.
 */
export type Disk = 'public' | 'local' | 'signatures';

const root = () => path.resolve(config.storageDir);

/** Resolve inside the disk folder; refuses anything that escapes it. */
function resolve(disk: Disk, relative: string): string {
  const base = path.join(root(), disk);
  const full = path.resolve(base, relative);
  if (full !== base && !full.startsWith(base + path.sep)) throw new Error('Invalid file path.');
  return full;
}

export async function put(disk: Disk, relative: string, data: Buffer | Uint8Array): Promise<string> {
  const full = resolve(disk, relative);
  await fs.mkdir(path.dirname(full), { recursive: true });
  await fs.writeFile(full, data);
  return relative;
}

export async function get(disk: Disk, relative: string): Promise<Buffer | null> {
  try {
    return await fs.readFile(resolve(disk, relative));
  } catch {
    return null;
  }
}

export async function exists(disk: Disk, relative: string | null | undefined): Promise<boolean> {
  if (!relative) return false;
  try {
    return (await fs.stat(resolve(disk, relative))).isFile();
  } catch {
    return false;
  }
}

export async function remove(disk: Disk, relative: string | null | undefined): Promise<void> {
  if (!relative) return;
  try {
    await fs.unlink(resolve(disk, relative));
  } catch { /* already gone */ }
}

const MIME: Record<string, string> = {
  png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', webp: 'image/webp', gif: 'image/gif', svg: 'image/svg+xml',
  pdf: 'application/pdf', csv: 'text/csv', xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', zip: 'application/zip',
};
export const mimeFor = (file: string) => MIME[path.extname(file).slice(1).toLowerCase()] ?? 'application/octet-stream';

/** Detect image type from the first bytes, never trusting the file name. */
export function sniffImage(buf: Buffer): 'png' | 'jpg' | 'webp' | 'gif' | null {
  if (buf.length >= 8 && buf.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return 'png';
  if (buf.length >= 3 && buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return 'jpg';
  if (buf.length >= 12 && buf.subarray(0, 4).toString() === 'RIFF' && buf.subarray(8, 12).toString() === 'WEBP') return 'webp';
  if (buf.length >= 6 && ['GIF87a', 'GIF89a'].includes(buf.subarray(0, 6).toString())) return 'gif';
  return null;
}

export function sniffPdf(buf: Buffer): boolean {
  return buf.length > 4 && buf.subarray(0, 4).toString() === '%PDF';
}

/** Pixel size of a PNG or JPEG, read from the file header. */
export function imageSize(buf: Buffer): { width: number; height: number } | null {
  if (sniffImage(buf) === 'png' && buf.length >= 24) return { width: buf.readUInt32BE(16), height: buf.readUInt32BE(20) };
  if (sniffImage(buf) === 'jpg') {
    let i = 2;
    while (i + 9 < buf.length) {
      if (buf[i] !== 0xff) { i++; continue; }
      const marker = buf[i + 1];
      if (marker >= 0xc0 && marker <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marker)) return { height: buf.readUInt16BE(i + 5), width: buf.readUInt16BE(i + 7) };
      i += 2 + buf.readUInt16BE(i + 2);
    }
  }
  return null;
}
