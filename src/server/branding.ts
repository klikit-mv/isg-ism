import { randomBytes } from 'node:crypto';
import { recordAudit } from './audit';
import { ValidationError } from './errors';
import { getSetting, setSetting } from './settings';
import { imageSize, put, remove, sniffImage } from './storage';

/** Save an uploaded website logo (PNG/JPEG, at most 2000 x 2000). */
export async function saveLogo(file: File, actorId: number): Promise<string> {
  if (file.size > 2048 * 1024) throw new ValidationError({ logo: 'The logo must not be greater than 2048 kilobytes.' });
  const bytes = Buffer.from(await file.arrayBuffer());
  const kind = sniffImage(bytes);
  const size = imageSize(bytes);
  if ((kind !== 'png' && kind !== 'jpg') || !size) throw new ValidationError({ logo: 'The logo must be a PNG or JPEG image.' });
  if (size.width > 2000 || size.height > 2000) throw new ValidationError({ logo: 'The logo can be at most 2000 × 2000 pixels.' });

  const old = await getSetting('site_logo_path');
  const path = await put('public', `branding/logo-${randomBytes(6).toString('hex')}.${kind}`, bytes);
  await setSetting('site_logo_path', path, actorId);
  await remove('public', old);
  await recordAudit('settings.logo_updated', null, {}, actorId);
  return 'The website logo was updated.';
}

export async function removeLogo(actorId: number): Promise<string | null> {
  const old = await getSetting('site_logo_path');
  if (!old) return null;
  await setSetting('site_logo_path', null, actorId);
  await remove('public', old);
  await recordAudit('settings.logo_removed', null, {}, actorId);
  return 'The website logo was removed.';
}
