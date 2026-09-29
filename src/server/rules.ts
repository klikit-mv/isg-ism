import { and, eq, ne, isNull, type SQL } from 'drizzle-orm';
import type { AnyMySqlColumn, MySqlTable } from 'drizzle-orm/mysql-core';
import { db } from '@/db';
import type { Rule } from '@/lib/validate';

/** Value must not already exist in `column` (ignoring one row when editing). */
export function unique(
  table: MySqlTable,
  column: AnyMySqlColumn,
  ignoreId?: number | null,
  idColumn?: AnyMySqlColumn,
  message?: string,
): Rule {
  return async (value, ctx) => {
    const filters: SQL[] = [eq(column, value)];
    if (ignoreId && idColumn) filters.push(ne(idColumn, ignoreId));
    const [row] = await db.select({ v: column }).from(table).where(and(...filters)).limit(1);
    return row ? message ?? `The ${ctx.label} has already been taken.` : null;
  };
}

/** Value must exist in `column` (soft-deleted rows do not count when `deletedAt` is given). */
export function exists(table: MySqlTable, column: AnyMySqlColumn, deletedAt?: AnyMySqlColumn): Rule {
  return async (value, ctx) => {
    const filters: SQL[] = [eq(column, value)];
    if (deletedAt) filters.push(isNull(deletedAt));
    const [row] = await db.select({ v: column }).from(table).where(and(...filters)).limit(1);
    return row ? null : `The selected ${ctx.label} is invalid.`;
  };
}
