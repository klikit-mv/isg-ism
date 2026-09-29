import { db, schema } from '@/db';
import { config } from '@/lib/config';
import { hashPin, assignRole, syncPermissions } from './users';
import { DEMO_ACCOUNTS, demoPin } from './demo-accounts';
import { ensureDefaultTemplates } from './certificate-templates';
import { and, eq } from 'drizzle-orm';

/**
 * Sample data for development. Refuses to run in production.
 * Grows as modules are added.
 */
export async function seedDemo(log: (m: string) => void = () => {}): Promise<void> {
  if (config.isProduction) throw new Error('The demo data is never created in production.');
  await ensureDefaultTemplates();
  await ensureDemoBadges();
  const pin = demoPin();
  const password = await hashPin(pin);
  const now = new Date();

  const user = async (o: { nationalId: string; name: string; email: string; roles: ('admin' | 'leader' | 'parent' | 'student')[]; studentId?: number; permissions?: ('canVerifyPayments' | 'canManageShop' | 'canProcessDelivery' | 'canManageFees')[] }) => {
    const [existing] = await db.select().from(schema.users).where(eq(schema.users.nationalId, o.nationalId)).limit(1);
    if (existing) return existing.id;
    const [row] = await db.insert(schema.users).values({
      name: o.name, nationalId: o.nationalId, email: o.email, password, status: 'active', verifiedAt: now, studentId: o.studentId ?? null, createdAt: now, updatedAt: now,
    }).$returningId();
    for (const r of o.roles) await assignRole(row.id, r);
    if (o.permissions) await syncPermissions(row.id, o.permissions);
    return row.id;
  };

  const adminId = await user({ nationalId: DEMO_ACCOUNTS[0].nationalId, name: process.env.SCOUT_ADMIN_NAME ?? 'System Administrator', email: 'admin@example.com', roles: ['admin'] });
  log(`Sample admin: ${DEMO_ACCOUNTS[0].nationalId} / PIN ${pin}`);
  const leaderId = await user({ nationalId: 'A100001', name: 'Hassan Leader', email: 'leader@example.com', roles: ['leader'] });
  const treasurerId = await user({ nationalId: 'A100002', name: 'Mariyam Treasurer', email: 'treasurer@example.com', roles: ['leader'], permissions: ['canVerifyPayments', 'canManageShop', 'canProcessDelivery', 'canManageFees'] });

  const sections = ['Pre Cub', 'Cub Scout', 'Scout', 'Rover'] as const;
  const names = ['Ali', 'Aisha', 'Hamza', 'Zaina', 'Yusuf', 'Mariyam', 'Ibrahim', 'Fathimath', 'Ahmed', 'Nadha', 'Shan', 'Leela', 'Imran', 'Sana', 'Rasheed', 'Huda'];
  const students: { id: number; section: string; name: string; nationalId: string }[] = [];
  let n = 0;
  for (const section of sections) {
    for (let i = 1; i <= 4; i++) {
      n++;
      const special = section === 'Scout' && i === 1;
      const nationalId = special ? 'A200001' : `A2${String(n + 1).padStart(5, '0')}`;
      const name = special ? 'Ibrahim Scout' : `${names[n - 1]} ${section.split(' ')[0]}`;
      const [existing] = await db.select().from(schema.students).where(eq(schema.students.nationalId, nationalId)).limit(1);
      let id = existing?.id;
      if (!id) {
        [{ id }] = await db.insert(schema.students).values({
          indexNumber: `IX${String(1000 + n)}`, name, nationalId, email: `scout${n}@example.com`, gender: n % 2 ? 'Male' : 'Female',
          permanentAddress: 'Hithadhoo, Addu', presentAddress: 'Hithadhoo, Addu', dateOfBirth: `${2008 + Math.floor(n / 3)}-0${1 + (n % 9)}-1${n % 9}`,
          parentName: 'Parent Name', primaryMobile: `77${String(10000 + n)}`, section, status: 'active', verifiedAt: now, createdAt: now, updatedAt: now,
        }).$returningId();
        await user({ nationalId, name, email: `scout${n}@example.com`, roles: ['student'], studentId: id });
      }
      students.push({ id, section, name, nationalId });
    }
  }

  const [waiting] = await db.select().from(schema.students).where(eq(schema.students.nationalId, 'A2999999')).limit(1);
  if (!waiting) {
    await db.insert(schema.students).values({ indexNumber: 'IX9999', name: 'Waiting Scout', nationalId: 'A2999999', email: 'waiting@example.com', gender: 'Male', permanentAddress: 'Addu', presentAddress: 'Addu', dateOfBirth: '2013-04-05', parentName: 'Parent', primaryMobile: '7799999', section: 'Scout', status: 'pending', createdAt: now, updatedAt: now });
  }

  const group = async (name: string, type: string | null, leader: number, members: number[]) => {
    const [existing] = await db.select().from(schema.groups).where(eq(schema.groups.name, name)).limit(1);
    if (existing) return existing.id;
    const [g] = await db.insert(schema.groups).values({ name, type, ownerId: adminId, createdAt: now, updatedAt: now }).$returningId();
    await db.insert(schema.groupLeaders).values({ groupId: g.id, userId: leader, createdAt: now, updatedAt: now });
    for (const m of members) await db.insert(schema.groupMembers).values({ groupId: g.id, studentId: m, createdAt: now, updatedAt: now });
    return g.id;
  };
  const eagle = await group('Eagle Patrol', 'Patrol', leaderId, students.filter((s) => s.section === 'Scout').map((s) => s.id));
  await group('Lion Six', 'Six', treasurerId, students.filter((s) => s.section === 'Cub Scout').map((s) => s.id));
  const rover = students.find((s) => s.section === 'Rover')!;
  await db.insert(schema.groupAssistantLeaders).values({ groupId: eagle, studentId: rover.id, createdAt: now, updatedAt: now }).onDuplicateKeyUpdate({ set: { updatedAt: now } });

  const parentId = await user({ nationalId: 'A100003', name: 'Aminath Parent', email: 'parent@example.com', roles: ['parent'] });
  for (const s of [students.find((x) => x.section === 'Scout')!, students.find((x) => x.section === 'Cub Scout')!]) {
    const [link] = await db.select().from(schema.parentStudentLinks).where(and(eq(schema.parentStudentLinks.parentUserId, parentId), eq(schema.parentStudentLinks.studentId, s.id))).limit(1);
    if (!link) await db.insert(schema.parentStudentLinks).values({ parentUserId: parentId, studentId: s.id, status: 'approved', createdAt: now, updatedAt: now });
  }
  log('Demo data is ready.');
}

/** A few proficiency badges so requests can be tried out. */
async function ensureDemoBadges(): Promise<void> {
  const now = new Date();
  const badges = [
    { name: 'Camper', code: 'CAMPER', section: 'Scout' }, { name: 'Cook', code: 'COOK', section: 'Scout' },
    { name: 'Explorer', code: 'EXPLORER', section: 'Cub Scout' }, { name: 'First Aider', code: 'FIRSTAID', section: 'Rover' },
  ];
  for (const b of badges) {
    const [existing] = await db.select({ id: schema.badges.id }).from(schema.badges).where(eq(schema.badges.code, b.code)).limit(1);
    if (existing) continue;
    await db.insert(schema.badges).values({ ...b, badgeId: `B${b.code.slice(0, 4)}`, category: 'proficiency', createdAt: now, updatedAt: now });
  }
}
