import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { authenticate, createSession, userForSession } from '@/server/auth';
import { scrub, recordAudit } from '@/server/audit';
import { decryptString, encryptString } from '@/server/crypto';
import { ScoutError } from '@/server/errors';
import { createLink, setLinkStatus } from '@/server/parent-links';
import { getSetting, setSetting } from '@/server/settings';
import { createUser, deleteUser, resetPin, updateUser } from '@/server/user-admin';
import { loadUser } from '@/server/users';
import { saveLogo, removeLogo } from '@/server/branding';
import { logoPath } from '@/server/settings';
import { readFileSync } from 'node:fs';
import { makeAdmin, makeStudent, makeUser } from '../factories';

describe('user administration', () => {
  it('creates a user with roles and permissions, and updates them', async () => {
    const admin = await makeAdmin();
    const user = await createUser({ name: 'New Leader', nationalId: 'l555555', email: 'l@example.com', pin: '4321', roles: ['leader'], permissions: ['canManageShop'] }, admin);
    expect(user.nationalId).toBe('L555555');
    expect(user.roles).toEqual(['leader']);
    await updateUser(user.id, { name: 'Renamed', email: null, status: 'active', roles: ['leader', 'parent'], permissions: [] }, admin);
    const after = (await loadUser(user.id))!;
    expect(after.roles.sort()).toEqual(['leader', 'parent']);
    expect(after.permissions).toEqual([]);
  });

  it('resetting a PIN signs the user out everywhere', async () => {
    const admin = await makeAdmin();
    const user = await makeUser({ nationalId: 'A123456' });
    const { token } = await createSession(user.id, null, null);
    await resetPin(user.id, '7777', admin);
    expect(await userForSession(token)).toBeNull();
    await authenticate('A123456', '7777', '1.1.1.1');
  });

  it('cannot delete your own account; deleted users cannot sign in', async () => {
    const admin = await makeAdmin();
    const other = await makeUser({ nationalId: 'A654321' });
    await expect(deleteUser(admin.id, admin)).rejects.toThrow(ScoutError);
    await deleteUser(other.id, admin);
    await expect(authenticate('A654321', '1234', '1.1.1.1')).rejects.toBeTruthy();
  });
});

describe('parent links', () => {
  it('a scout can have only one pending or approved parent', async () => {
    const admin = await makeAdmin();
    const scout = await makeStudent();
    const [p1, p2] = [await makeUser(), await makeUser()];
    await createLink(p1, scout, 'approved', admin);
    await expect(createLink(p2, scout, 'approved', admin)).rejects.toThrow('This scout is already added under another parent.');
    const link = (await db.select().from(schema.parentStudentLinks))[0];
    await setLinkStatus(link.id, 'inactive', admin);
    await createLink(p2, scout, 'approved', admin);
    expect((await loadUser(p2.id))!.roles).toContain('parent');
  });
});

describe('secrets and audit', () => {
  it('settings holding secrets are stored encrypted and read back', async () => {
    process.env.APP_KEY = `base64:${Buffer.alloc(32, 7).toString('base64')}`;
    await setSetting('telegram_bot_token', '12345:ABC');
    const [row] = await db.select().from(schema.settings).where(eq(schema.settings.key, 'telegram_bot_token'));
    expect(row.value).not.toContain('12345');
    expect(await getSetting('telegram_bot_token')).toBe('12345:ABC');
    await setSetting('footer_text', 'Hello');
    expect((await db.select().from(schema.settings).where(eq(schema.settings.key, 'footer_text')))[0].value).toBe('Hello');
  });

  it('reads a value encrypted by the old Laravel portal', () => {
    process.env.APP_KEY = `base64:${Buffer.alloc(32, 'a').toString('base64')}`;
    const laravel = readFileSync(process.env.LARAVEL_ENC_FIXTURE ?? 'tests/fixtures/laravel-encrypted.txt', 'utf8').trim();
    expect(decryptString(laravel)).toBe('telegram-secret-123');
    expect(decryptString(encryptString('round trip'))).toBe('round trip');
    expect(decryptString(laravel.slice(0, -4) + 'AAAA')).toBeNull();
  });

  it('never writes PINs or tokens to the audit log', async () => {
    expect(scrub({ pin: '1234', Token: 'x', keep: 1, nested: { password: 'p', ok: true } })).toEqual({ keep: 1, nested: { ok: true } });
    await recordAudit('x.test', { type: 'user', id: 1 }, { pin: '1234', national_id: 'A1' }, null);
    const [log] = await db.select().from(schema.auditLogs);
    expect(log.details).toEqual({ national_id: 'A1' });
    expect(log.entityType).toBe('User');
  });
});

describe('website logo', () => {
  const png = (w: number, h: number) => {
    const b = Buffer.alloc(33);
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]).copy(b);
    b.writeUInt32BE(w, 16);
    b.writeUInt32BE(h, 20);
    return new File([new Uint8Array(b)], 'logo.png', { type: 'image/png' });
  };

  it('accepts a PNG, replaces it and removes it', async () => {
    const admin = await makeAdmin();
    await saveLogo(png(200, 200), admin.id);
    const first = await logoPath();
    expect(first).toMatch(/^branding\/logo-.*\.png$/);
    await saveLogo(png(300, 300), admin.id);
    expect(await logoPath()).not.toBe(first);
    expect(await removeLogo(admin.id)).toBe('The website logo was removed.');
    expect(await logoPath()).toBeNull();
  });

  it('refuses a non-image and an oversized picture', async () => {
    const admin = await makeAdmin();
    await expect(saveLogo(new File(['not an image'], 'logo.png'), admin.id)).rejects.toThrow();
    await expect(saveLogo(png(2500, 100), admin.id)).rejects.toBeTruthy();
  });
});
