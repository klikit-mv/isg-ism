import './env.mts';
import { migrate } from 'drizzle-orm/mysql2/migrator';
import { db, getPool } from '../src/db';

/** Apply the SQL migrations in ./drizzle. Safe to run on every deploy. */
await migrate(db, { migrationsFolder: './drizzle' });
console.log('Database is up to date.');
await getPool().end();
