import { randomUUID } from 'node:crypto';
import path from 'node:path';
import bcrypt from 'bcryptjs';
import { migrate } from 'drizzle-orm/mysql2/migrator';
import type { RowDataPacket, ResultSetHeader } from 'mysql2/promise';
import { db, getPool } from '@/db';

/**
 * Runs whenever the server starts (see instrumentation.ts) and from `npm run db:migrate`:
 * applies migrations, clears expired sign-ins, adds default certificate templates and, on a
 * brand new install, creates the first administrator from environment variables.
 * Safe to run repeatedly and from several processes at once.
 */
export async function runBootstrap(log: (message: string) => void = console.log): Promise<void> {
  const pool = getPool();
  const lock = await pool.getConnection();
  try {
    await lock.query('SELECT GET_LOCK(?, 120)', ['scout_bootstrap']);
    await migrate(db, { migrationsFolder: path.join(process.cwd(), 'drizzle') });
    log('Database is up to date.');

    await pool.query('DELETE FROM app_sessions WHERE expires_at < UTC_TIMESTAMP()');
    await pool.query('DELETE FROM rate_limits WHERE resets_at < UTC_TIMESTAMP()');

    const names = { badge: 'Badge certificate', general: 'General certificate', leadership: 'Leadership certificate' };
    for (const [type, name] of Object.entries(names)) {
      const [[row]] = await pool.query<RowDataPacket[]>('SELECT COUNT(*) AS n FROM certificate_templates WHERE type = ?', [type]);
      if (Number(row.n) === 0) {
        const now = new Date();
        await pool.query(
          'INSERT INTO certificate_templates (uuid, template_id, name, type, google_slide_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
          [randomUUID(), `TPL-${randomUUID().slice(0, 6).toUpperCase()}`, name, type, `local-${type}`, now, now],
        );
      }
    }

    const nationalId = (process.env.SCOUT_ADMIN_NATIONAL_ID ?? '').trim().toUpperCase();
    const pin = process.env.SCOUT_ADMIN_PIN ?? '';
    if (nationalId && pin && process.env.NODE_ENV === 'production') {
      const [[admins]] = await pool.query<RowDataPacket[]>("SELECT COUNT(*) AS n FROM user_roles WHERE role = 'admin'");
      if (Number(admins.n) === 0) {
        if (pin.length < 4) throw new Error('SCOUT_ADMIN_PIN must be at least 4 characters.');
        const now = new Date();
        const password = await bcrypt.hash(pin, Number(process.env.BCRYPT_ROUNDS ?? 12));
        const [existing] = await pool.query<RowDataPacket[]>('SELECT id FROM users WHERE national_id = ? LIMIT 1', [nationalId]);
        let id: number = existing[0]?.id;
        if (id) {
          await pool.query("UPDATE users SET password = ?, status = 'active', verified_at = ?, deleted_at = NULL, updated_at = ? WHERE id = ?", [password, now, now, id]);
        } else {
          const [ins] = await pool.query<ResultSetHeader>(
            "INSERT INTO users (uuid, name, national_id, email, password, status, verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?)",
            [randomUUID(), process.env.SCOUT_ADMIN_NAME || 'Administrator', nationalId, process.env.SCOUT_ADMIN_EMAIL || null, password, now, now, now],
          );
          id = ins.insertId;
        }
        await pool.query('INSERT INTO user_roles (user_id, role, created_at, updated_at) VALUES (?, ?, ?, ?)', [id, 'admin', now, now]);
        log(`First administrator ${nationalId} created. Sign in, change the PIN under Profile, then remove SCOUT_ADMIN_PIN from the environment.`);
      }
    }
  } finally {
    await lock.query('SELECT RELEASE_LOCK(?)', ['scout_bootstrap']).catch(() => undefined);
    lock.release();
  }
}

/** A readable reason for a failed database call (the driver's error is hidden inside "Failed query"). */
export function explain(error: unknown): string {
  const e = error as { cause?: { code?: string; message?: string }; code?: string; message?: string };
  const cause = e.cause ?? e;
  return [cause.code, cause.message].filter(Boolean).join(': ') || String(error);
}
