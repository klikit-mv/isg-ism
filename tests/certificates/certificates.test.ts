import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { createBadge, deleteBadge } from '@/server/badges';
import { currentYear } from '@/server/certificate-numbers';
import { afterAttendanceSave } from '@/server/certificate-hooks';
import { createTemplate, deleteTemplate } from '@/server/certificate-templates';
import {
  approveRequest, bulkCreateGeneral, certificatePdf, findCertificateByNumber, generateApproved, generateBadgeCertificate, generateGeneralCertificate, generateLeadershipCertificate,
  listCertificates, listRequests, regenerateCertificate, rejectRequest, requestBadge, signCertificate,
} from '@/server/certificates';
import { createLeadership } from '@/server/leadership';
import { markAttendance } from '@/server/attendance';
import { makeActivity, makeAdmin, makeGroup, makeLeader, makeParentOf, makeStudent } from '../factories';

const year = () => currentYear();
async function setup() {
  const admin = await makeAdmin();
  const badgeTpl = await createTemplate({ name: 'Badge', type: 'badge' }, admin);
  const generalTpl = await createTemplate({ name: 'General', type: 'general' }, admin);
  await createTemplate({ name: 'Lead', type: 'leadership' }, admin);
  return { admin, badgeTpl, generalTpl };
}

describe('certificate numbers', () => {
  it('section proficiency badges share one yearly sequence; other badges count on their own', async () => {
    const { admin } = await setup();
    const scout = await makeStudent({ section: 'Scout' });
    const other = await makeStudent({ section: 'Scout' });
    const a = await createBadge({ name: 'Camper', code: 'CAMP', section: 'Scout', category: 'proficiency' }, null, admin);
    const b = await createBadge({ name: 'Cook', code: 'COOK', section: 'Scout', category: 'proficiency' }, null, admin);
    const special = await createBadge({ name: 'Jamboree', code: 'JAM', category: 'special', number_prefix: 'jb' }, null, admin);

    const c1 = await generateBadgeCertificate(scout, a, '2026-03-01', null, admin);
    const c2 = await generateBadgeCertificate(other, b, '2026-03-02', null, admin);
    const c3 = await generateBadgeCertificate(scout, special, '2026-03-03', null, admin);
    const c4 = await generateBadgeCertificate(other, special, '2026-03-03', null, admin);
    expect(c1.certNumber).toBe(`SCOUT-${year()}-0001`);
    expect(c2.certNumber).toBe(`SCOUT-${year()}-0002`);
    expect(c3.certNumber).toBe(`JB-${year()}-0001`);
    expect(c4.certNumber).toBe(`JB-${year()}-0002`);
  });

  it('an admin can choose the next number', async () => {
    const { admin } = await setup();
    const scout = await makeStudent();
    const badge = await createBadge({ name: 'Camper', code: 'CAMP', section: 'Scout', next_number: 40 }, null, admin);
    const cert = await generateBadgeCertificate(scout, badge, '2026-03-01', null, admin);
    expect(cert.certNumber).toBe(`SCOUT-${year()}-0040`);
  });

  it('general and leadership numbers are separate sequences', async () => {
    const { admin, generalTpl } = await setup();
    const scout = await makeStudent();
    const g = await generateGeneralCertificate(scout, 'Well done', '2026-03-01', generalTpl, admin);
    const record = await createLeadership({ student_id: scout.id, patrol_or_six: 'Eagles', start_date: '2026-01-01' }, admin);
    const l = await generateLeadershipCertificate(record.id, admin);
    expect(g.certNumber).toBe(`CERT-${year()}-0001`);
    expect(l.certNumber).toBe(`LEAD-${year()}-0001`);
    const again = await generateLeadershipCertificate(record.id, admin);
    expect(again.id).toBe(l.id);
    expect(again.certNumber).toBe(l.certNumber);
  });
});

describe('certificate documents', () => {
  it('renders a real PDF, stores it, and can sign and regenerate it', async () => {
    const { admin, generalTpl } = await setup();
    const leader = await makeLeader();
    const scout = await makeStudent();
    await makeGroup({ leaders: [leader], members: [scout] });
    const cert = await generateGeneralCertificate(scout, 'Camp leader', '2026-03-01', generalTpl, admin);
    const pdf = await certificatePdf(cert);
    expect(pdf?.subarray(0, 4).toString()).toBe('%PDF');

    const signed = await signCertificate(cert, leader);
    expect(signed.path).toBe(cert.path);
    const [row] = await db.select().from(schema.certificates).where(eq(schema.certificates.id, cert.id));
    expect(row.status).toBe('verified');
    expect(row.verifiedBy).toBe(leader.id);
    await regenerateCertificate(row, admin);
    expect((await findCertificateByNumber(cert.certNumber.toLowerCase()))?.id).toBe(cert.id);
  });

  it('a leader outside the scout\'s group cannot sign', async () => {
    const { admin, generalTpl } = await setup();
    const stranger = await makeLeader();
    const cert = await generateGeneralCertificate(await makeStudent(), 'Award', '2026-03-01', generalTpl, admin);
    await expect(signCertificate(cert, stranger)).rejects.toThrow('cannot sign');
  });

  it('templates must match the type and be active; used templates cannot be deleted', async () => {
    const { admin, badgeTpl, generalTpl } = await setup();
    const scout = await makeStudent();
    await expect(generateGeneralCertificate(scout, 'X', '2026-03-01', badgeTpl, admin)).rejects.toThrow('not general');
    await generateGeneralCertificate(scout, 'X', '2026-03-01', generalTpl, admin);
    await expect(deleteTemplate(generalTpl, admin)).rejects.toThrow('cannot be deleted');
    await expect(generateGeneralCertificate(scout, ' ', '2026-03-01', generalTpl, admin)).rejects.toThrow('needs a title');
  });
});

describe('badge requests', () => {
  it('request → approve → generate; duplicates and wrong states are refused', async () => {
    const { admin } = await setup();
    const leader = await makeLeader();
    const scout = await makeStudent();
    await makeGroup({ leaders: [leader], members: [scout] });
    const badge = await createBadge({ name: 'Camper', code: 'CAMP', section: 'Scout' }, null, admin);

    const request = await requestBadge(scout, badge, leader);
    await expect(requestBadge(scout, badge, leader)).rejects.toThrow('already has a request');
    await expect(generateApproved(request, leader, '2026-03-01')).rejects.toThrow('Only approved');
    await approveRequest(request, leader, 'Well done');
    await expect(rejectRequest(request, leader)).rejects.toThrow('Only requested');
    const cert = await generateApproved(request, leader, '2026-03-01');
    const [done] = await db.select().from(schema.badgeRequests).where(eq(schema.badgeRequests.id, request.id));
    expect(done.status).toBe('generated');
    expect(done.certificateNumber).toBe(cert.certNumber);
    await expect(deleteBadge(badge, admin)).rejects.toThrow('cannot be deleted');
  });

  it('parents request for their child but cannot decide; strangers cannot request', async () => {
    const { admin } = await setup();
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const stranger = await makeParentOf(await makeStudent());
    const badge = await createBadge({ name: 'Camper', code: 'CAMP', section: 'Scout' }, null, admin);
    await expect(requestBadge(scout, badge, stranger)).rejects.toThrow('cannot request');
    const request = await requestBadge(scout, badge, parent);
    expect((await listRequests(parent, { page: 1 })).rows).toHaveLength(1);
    expect((await listRequests(stranger, { page: 1 })).rows).toHaveLength(0);
    expect(request.status).toBe('requested');
  });
});

describe('scope and bulk', () => {
  it('lists only certificates of accessible scouts', async () => {
    const { admin, generalTpl } = await setup();
    const mine = await makeStudent();
    const theirs = await makeStudent();
    const parent = await makeParentOf(mine);
    await generateGeneralCertificate(mine, 'A', '2026-03-01', generalTpl, admin);
    await generateGeneralCertificate(theirs, 'B', '2026-03-01', generalTpl, admin);
    expect((await listCertificates(parent, { page: 1 })).rows.map((c) => c.studentId)).toEqual([mine.id]);
    expect((await listCertificates(admin, { page: 1 })).rows).toHaveLength(2);
  });

  it('bulk issue reports failures without undoing successes', async () => {
    const { admin, generalTpl } = await setup();
    const leader = await makeLeader();
    const mine = await makeStudent();
    const theirs = await makeStudent();
    await makeGroup({ leaders: [leader], members: [mine] });
    const result = await bulkCreateGeneral([mine.id, theirs.id], 'Camp', '2026-03-01', generalTpl, leader);
    expect(result.created).toHaveLength(1);
    expect(Object.keys(result.failed)).toEqual([theirs.name]);
  });
});

describe('activity certificates', () => {
  it('issues once to present and late scouts after the register is saved', async () => {
    const { admin, generalTpl } = await setup();
    const a = await makeStudent();
    const b = await makeStudent();
    const c = await makeStudent();
    const activity = await makeActivity({ all: true });
    await db.update(schema.activities).set({ certificateTemplateId: generalTpl.id }).where(eq(schema.activities.id, activity.id));
    const [fresh] = await db.select().from(schema.activities).where(eq(schema.activities.id, activity.id));
    await markAttendance(fresh, admin, { [a.id]: { status: 'Present' }, [b.id]: { status: 'Late' }, [c.id]: { status: 'Absent' } });

    expect(await afterAttendanceSave(fresh, admin)).toEqual({ message: '2 certificate(s) issued.' });
    expect(await afterAttendanceSave(fresh, admin)).toEqual({});
    expect(await db.select().from(schema.certificates)).toHaveLength(2);
  });
});
