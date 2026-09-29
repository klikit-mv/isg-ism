import { drizzle, type MySql2Database } from 'drizzle-orm/mysql2';
import mysql, { type Pool, type PoolOptions } from 'mysql2/promise';
import * as schema from './schema';

export type Db = MySql2Database<typeof schema>;

function options(): PoolOptions {
  const common: PoolOptions = {
    connectionLimit: Number(process.env.DB_POOL ?? 8),
    timezone: 'Z',
    decimalNumbers: false,
    dateStrings: false,
  };
  if (process.env.DATABASE_URL) {
    return { uri: process.env.DATABASE_URL, ...common };
  }
  return {
    host: process.env.DB_HOST ?? '127.0.0.1',
    port: Number(process.env.DB_PORT ?? 3306),
    database: process.env.DB_DATABASE ?? 'scout',
    user: process.env.DB_USERNAME ?? 'root',
    password: process.env.DB_PASSWORD ?? '',
    ...common,
  };
}

const globalForDb = globalThis as unknown as { __pool?: Pool; __db?: Db };

/** One pool per process (kept across hot reloads in development). */
export function getPool(): Pool {
  return (globalForDb.__pool ??= mysql.createPool(options()));
}

export const db: Db = (globalForDb.__db ??= drizzle(getPool(), { schema, mode: 'default' }));
export { schema };

/** Transaction handle or the main connection. */
export type Tx = Parameters<Parameters<Db['transaction']>[0]>[0];
export type DbLike = Db | Tx;
