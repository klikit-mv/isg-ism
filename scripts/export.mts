import './env.mts';
import { promises as fs } from 'node:fs';
import path from 'node:path';
import { getPool } from '../src/db';
import { LEDGER_TYPES, exportLedger, isLedger } from '../src/server/ledger-export';

/** npm run scout:export -- payments [--path=./out.xlsx] [--format=csv] */
const [type, ...flags] = process.argv.slice(2);
const flag = (name: string) => flags.find((f) => f.startsWith(`--${name}=`))?.slice(name.length + 3);
if (!type || !isLedger(type)) {
  console.error(`Usage: npm run scout:export -- <type> [--path=file] [--format=xlsx|csv]\nTypes: ${LEDGER_TYPES.join(', ')}`);
  process.exit(1);
}
const format = flag('format') === 'csv' ? 'csv' : 'xlsx';
const target = path.resolve(flag('path') ?? `${type}-${new Date().toISOString().slice(0, 10)}.${format}`);
await fs.writeFile(target, await exportLedger(type, format));
console.log(`Exported ${type} to ${target}`);
await getPool().end();
