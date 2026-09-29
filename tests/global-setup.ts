import mysql from 'mysql2/promise';
import { drizzle } from 'drizzle-orm/mysql2';
import { migrate } from 'drizzle-orm/mysql2/migrator';

/** Start every run from a clean, fully migrated test database. */
export default async function setup() {
  const conn = await mysql.createConnection({
    host: process.env.TEST_DB_HOST ?? '127.0.0.1',
    port: Number(process.env.TEST_DB_PORT ?? 3306),
    user: process.env.TEST_DB_USERNAME ?? 'scout',
    password: process.env.TEST_DB_PASSWORD ?? 'secret',
    database: process.env.TEST_DB_DATABASE ?? 'scout_next_test',
    multipleStatements: true,
  });
  const [tables] = await conn.query<any[]>('SHOW TABLES');
  await conn.query('SET FOREIGN_KEY_CHECKS = 0');
  for (const row of tables) await conn.query(`DROP TABLE IF EXISTS \`${Object.values(row)[0]}\``);
  await conn.query('SET FOREIGN_KEY_CHECKS = 1');
  await migrate(drizzle(conn), { migrationsFolder: './drizzle' });
  await conn.end();
}
