// Runs before the site starts (npm start): applies database migrations and, on a brand new
// install, creates the first administrator from environment variables. Plain JavaScript on
// purpose, so it works on hosting that only installs production packages.
import { randomUUID } from 'node:crypto';
import bcrypt from 'bcryptjs';
import { drizzle } from 'drizzle-orm/mysql2';
import { migrate } from 'drizzle-orm/mysql2/migrator';
import mysql from 'mysql2/promise';

for (const file of ['.env.local', '.env']) {
  try {
    (await import('dotenv')).config({ path: file, quiet: true });
  } catch {
    // dotenv is a development tool; hosting panels set real environment variables.
  }
}

const pool = mysql.createPool(
  process.env.DATABASE_URL
    ? { uri: process.env.DATABASE_URL, timezone: 'Z' }
    : {
        host: process.env.DB_HOST ?? '127.0.0.1', port: Number(process.env.DB_PORT ?? 3306), database: process.env.DB_DATABASE ?? 'scout',
        user: process.env.DB_USERNAME ?? 'root', password: process.env.DB_PASSWORD ?? '', timezone: 'Z',
      },
);

try {
  await migrate(drizzle(pool), { migrationsFolder: './drizzle' });
  console.log('Database is up to date.');

  // Housekeeping (there is no cron on this hosting): drop expired sign-ins and old rate-limit rows.
  await pool.query('DELETE FROM app_sessions WHERE expires_at < UTC_TIMESTAMP()');
  await pool.query('DELETE FROM rate_limits WHERE resets_at < UTC_TIMESTAMP()');

  // One certificate template per type, so certificates can be issued straight away.
  const names = { badge: 'Badge certificate', general: 'General certificate', leadership: 'Leadership certificate' };
  for (const [type, name] of Object.entries(names)) {
    const [[{ n }]] = await pool.query('SELECT COUNT(*) AS n FROM certificate_templates WHERE type = ?', [type]);
    if (Number(n) === 0) {
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
    const [[{ admins }]] = await pool.query("SELECT COUNT(*) AS admins FROM user_roles WHERE role = 'admin'");
    if (Number(admins) === 0) {
      if (pin.length < 4) throw new Error('SCOUT_ADMIN_PIN must be at least 4 characters.');
      const now = new Date();
      const password = await bcrypt.hash(pin, Number(process.env.BCRYPT_ROUNDS ?? 12));
      const [existing] = await pool.query('SELECT id FROM users WHERE national_id = ? LIMIT 1', [nationalId]);
      let id = existing[0]?.id;
      if (id) {
        await pool.query("UPDATE users SET password = ?, status = 'active', verified_at = ?, deleted_at = NULL, updated_at = ? WHERE id = ?", [password, now, now, id]);
      } else {
        const [ins] = await pool.query(
          "INSERT INTO users (uuid, name, national_id, email, password, status, verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?)",
          [randomUUID(), process.env.SCOUT_ADMIN_NAME || 'Administrator', nationalId, process.env.SCOUT_ADMIN_EMAIL || null, password, now, now, now],
        );
        id = ins.insertId;
      }
      await pool.query('INSERT INTO user_roles (user_id, role, created_at, updated_at) VALUES (?, ?, ?, ?)', [id, 'admin', now, now]);
      console.log(`First administrator ${nationalId} created. Sign in, change the PIN under Profile, then remove SCOUT_ADMIN_PIN from the environment.`);
    }
  }
} catch (error) {
  console.error('Database setup failed:', error.message);
  process.exitCode = 1;
} finally {
  await pool.end();
}
