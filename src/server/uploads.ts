import { ValidationError } from './errors';
import { put, sniffImage, sniffPdf } from './storage';

const IMAGE_EXT = { png: 'png', jpg: 'jpg', webp: 'webp', gif: 'gif' } as const;

export const isUpload = (value: unknown): value is File => typeof File !== 'undefined' && value instanceof File && value.size > 0;

/**
 * Validate an uploaded picture by its real content (not the file name or the
 * browser's claim) and store it on the public disk. Returns the stored path.
 */
export async function storeImage(
  file: File,
  opts: { directory: string; name: string; field?: string; maxKb?: number; allowed?: ('png' | 'jpg' | 'webp')[] },
): Promise<string> {
  const field = opts.field ?? 'photo';
  const maxKb = opts.maxKb ?? 5120;
  const allowed = opts.allowed ?? ['png', 'jpg', 'webp'];
  if (file.size > maxKb * 1024) throw new ValidationError({ [field]: `The ${field} must not be greater than ${maxKb} kilobytes.` });

  const bytes = Buffer.from(await file.arrayBuffer());
  const kind = sniffImage(bytes);
  if (!kind || kind === 'gif' || !allowed.includes(kind)) {
    throw new ValidationError({ [field]: `The ${field} field must be an image (${allowed.map((a) => (a === 'jpg' ? 'jpg, jpeg' : a)).join(', ')}).` });
  }
  return put('public', `${opts.directory}/${opts.name}.${IMAGE_EXT[kind]}`, bytes);
}

/** A payment proof: an image or PDF, checked by content against the allowed list. */
export async function readProof(file: File, allowed: string[], maxKb: number): Promise<{ bytes: Buffer; ext: string; mime: string }> {
  if (file.size > maxKb * 1024) throw new ValidationError({ proof: `The proof must not be greater than ${maxKb} kilobytes.` });
  const bytes = Buffer.from(await file.arrayBuffer());
  const image = sniffImage(bytes);
  const ext = image ?? (sniffPdf(bytes) ? 'pdf' : null);
  const normalized = ext === 'jpg' ? ['jpg', 'jpeg'] : ext ? [ext] : [];
  if (!ext || !normalized.some((e) => allowed.includes(e))) {
    throw new ValidationError({ proof: `The proof must be a file of type: ${allowed.join(', ')}.` });
  }
  const mime = ext === 'pdf' ? 'application/pdf' : ext === 'jpg' ? 'image/jpeg' : `image/${ext}`;
  return { bytes, ext, mime };
}
