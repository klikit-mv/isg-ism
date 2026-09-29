import './env.mts';
import { randomInt } from 'node:crypto';
import { eq } from 'drizzle-orm';
import { db, getPool, schema } from '../src/db';
import { recordAudit } from '../src/server/audit';
import { ensureDefaultTemplates } from '../src/server/certificate-templates';
import { assignRole, hashPin } from '../src/server/users';

/** Create (or promote) the first administrator and print a one-time PIN. */
const [nationalIdArg, ...rest] = process.argv.slice(2);
const emailFlag = rest.find((a) => a.startsWith('--email='));
const name = rest.filter((a) => !a.startsWith('--')).join(' ');
if (!nationalIdArg || !name) {
  console.error('Usage: npm run scout:create-admin -- A1234567 "Full Name" [--email=you@example.org]');
  process.exit(1);
}

const nationalId = nationalIdArg.trim().toUpperCase();
const pin = String(randomInt(100000, 999999));
const now = new Date();
const password = await hashPin(pin);
const email = emailFlag?.slice(8) || null;

const [existing] = await db.select().from(schema.users).where(eq(schema.users.nationalId, nationalId)).limit(1);
let id: number;
if (existing) {
  id = existing.id;
  await db.update(schema.users).set({ name, email: email ?? existing.email, password, status: 'active', verifiedAt: now, legacyPinHash: null, legacyPinSalt: null, deletedAt: null, updatedAt: now }).where(eq(schema.users.id, id));
} else {
  [{ id }] = await db.insert(schema.users).values({ name, nationalId, email, password, status: 'active', verifiedAt: now, createdAt: now, updatedAt: now }).$returningId();
}
await assignRole(id, 'admin');
await recordAudit('user.admin_created', { type: 'user', id }, { national_id: nationalId }, null);
await ensureDefaultTemplates();

console.log(`Admin ${name} (${nationalId}) is ready.`);
console.log(`One-time PIN: ${pin}  — sign in and change it from Profile right away.`);
await getPool().end();
