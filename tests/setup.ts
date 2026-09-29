import { beforeEach } from 'vitest';
import { getPool } from '@/db';

/** Empty every table before each test. */
beforeEach(async () => {
  const pool = getPool();
  const [rows] = await pool.query<any[]>("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name NOT LIKE '\\_\\_%'");
  const conn = await pool.getConnection();
  try {
    await conn.query('SET FOREIGN_KEY_CHECKS = 0');
    for (const { t } of rows) await conn.query(`TRUNCATE TABLE \`${t}\``);
    await conn.query('SET FOREIGN_KEY_CHECKS = 1');
  } finally {
    conn.release();
  }
});
