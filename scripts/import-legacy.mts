import './env.mts';
import { promises as fs } from 'node:fs';
import { getPool } from '../src/db';
import { importLegacy, inspectLegacy } from '../src/server/legacy-import';

/** npm run scout:import-legacy -- --file=workbook.xlsx [--sheet=Students] [--force]   (a dry run unless --force) */
const flags = process.argv.slice(2);
const flag = (name: string) => flags.find((f) => f.startsWith(`--${name}=`))?.slice(name.length + 3);
const file = flag('file');
if (!file) {
  console.error('Usage: npm run scout:import-legacy -- --file=workbook.xlsx [--sheet=Name] [--force]');
  process.exit(1);
}
const bytes = await fs.readFile(file);
console.table((await inspectLegacy(bytes)).sheets);
const report = await importLegacy(bytes, { dryRun: !flags.includes('--force'), onlySheet: flag('sheet') ?? null });
console.log(report.dryRun ? 'DRY RUN: nothing was saved. Add --force to import.' : 'Imported.');
console.table(report.counts);
for (const e of report.errors.slice(0, 200)) console.log(`${e.sheet} row ${e.row}: ${e.message}`);
if (report.errors.length > 200) console.log(`… and ${report.errors.length - 200} more.`);
await getPool().end();
