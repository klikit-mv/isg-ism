import { eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import { config } from '@/lib/config';
import { normalize } from '@/lib/money';
import { decryptString, encryptString } from './crypto';
import * as storage from './storage';

/** Settings holding secrets are stored encrypted and never echoed back. */
export const ENCRYPTED = ['telegram_bot_token', 'google_service_account_json', 'google_oauth_client_secret', 'google_oauth_refresh_token'];

let cache: { at: number; values: Record<string, string | null> } | null = null;
const TTL_MS = 60_000;

async function all(): Promise<Record<string, string | null>> {
  if (cache && Date.now() - cache.at < TTL_MS) return cache.values;
  const rows = await db.select({ key: schema.settings.key, value: schema.settings.value }).from(schema.settings);
  cache = { at: Date.now(), values: Object.fromEntries(rows.map((r) => [r.key, r.value])) };
  return cache.values;
}

export const flushSettings = () => { cache = null; };

export async function getSetting(key: string, fallback: string | null = null): Promise<string | null> {
  const value = (await all())[key];
  if (value === null || value === undefined || value === '') return fallback;
  if (ENCRYPTED.includes(key)) return decryptString(value) ?? fallback;
  return value;
}

export async function setSetting(key: string, value: string | null, actorId: number | null = null): Promise<void> {
  const stored = value !== null && value !== '' && ENCRYPTED.includes(key) ? encryptString(value) : value;
  const now = new Date();
  await db.insert(schema.settings).values({ key, value: stored, updatedBy: actorId, createdAt: now, updatedAt: now })
    .onDuplicateKeyUpdate({ set: { value: stored, updatedBy: actorId, updatedAt: now } });
  flushSettings();
}

export async function forgetSetting(key: string): Promise<void> {
  await db.delete(schema.settings).where(eq(schema.settings.key, key));
  flushSettings();
}

export const defaultClassFee = async () => normalize(await getSetting('default_class_fee', config.defaultClassFee));
export const shopEnabled = async () => (await getSetting('shop_enabled', '1')) === '1';
export const proofMaxKb = async () => Number(await getSetting('proof_max_kb', String(config.proofMaxKb)));
export const proofMimes = async () =>
  ((await getSetting('proof_mimes', 'png,jpg,jpeg,pdf')) ?? '').toLowerCase().split(',').map((s) => s.trim()).filter(Boolean);

/** Path of the uploaded website logo (public disk), if it exists. */
export async function logoPath(): Promise<string | null> {
  const p = await getSetting('site_logo_path');
  return p && (await storage.exists('public', p)) ? p : null;
}

/** URL of the logo, versioned so a new upload shows straight away. */
export async function logoUrl(): Promise<string | null> {
  const p = await logoPath();
  if (!p) return null;
  const { createHash } = await import('node:crypto');
  return `/branding/logo?v=${createHash('md5').update(p).digest('hex').slice(0, 8)}`;
}

export const footerText = async () => (await getSetting('footer_text', `© ${new Date().getFullYear()} ${config.name}`)) as string;
