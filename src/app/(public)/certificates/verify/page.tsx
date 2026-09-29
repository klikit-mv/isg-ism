import { eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import { Badge } from '@/components/Badge';
import { Card } from '@/components/PageHeader';
import { formatDate } from '@/lib/dates';
import { CertificateStatus, CertificateType } from '@/lib/enums';
import { findCertificateByNumber } from '@/server/certificates';
import { canAccessStudent } from '@/server/scope';
import { getUser } from '@/server/session';

export const metadata = { title: 'Verify certificate' };

/** Anyone can check a certificate number. The National ID is only shown to people who can see the scout. */
export default async function VerifyPage({ searchParams }: { searchParams: Promise<{ cert_number?: string }> }) {
  const number = ((await searchParams).cert_number ?? '').trim();
  const cert = number ? await findCertificateByNumber(number) : null;
  const user = await getUser();
  let showId = false;
  let verifier: string | null = null;
  if (cert) {
    const [student] = await db.select({ id: schema.students.id, status: schema.students.status }).from(schema.students).where(eq(schema.students.id, cert.studentId));
    showId = !!user && !!student && (await canAccessStudent(user, student));
    if (cert.verifiedBy) verifier = (await db.select({ n: schema.users.name }).from(schema.users).where(eq(schema.users.id, cert.verifiedBy)))[0]?.n ?? null;
  }
  return (
    <>
      <h1 className="text-2xl font-bold">Verify a certificate</h1>
      <p className="mt-1 text-sm text-gray-500">Enter the certificate number printed on the certificate.</p>
      <form method="get" className="mt-4 flex max-w-md gap-2">
        <input name="cert_number" defaultValue={number} placeholder="SCOUT-2026-0001" className="input" required />
        <button type="submit" className="btn-primary">Check</button>
      </form>
      {number && !cert && <p className="mt-6 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200">No certificate was found with the number {number.toUpperCase()}.</p>}
      {cert && (
        <Card className="mt-6 max-w-2xl">
          <p className="mb-3 text-sm font-medium text-emerald-700 dark:text-emerald-300">This certificate is genuine.</p>
          <dl className="grid grid-cols-2 gap-4 text-sm">
            <div><dt className="text-gray-500">Number</dt><dd className="font-medium">{cert.certNumber}</dd></div>
            <div><dt className="text-gray-500">Awarded to</dt><dd className="font-medium">{cert.studentName}</dd></div>
            <div><dt className="text-gray-500">Certificate</dt><dd className="font-medium">{cert.title || cert.badgeName}</dd></div>
            <div><dt className="text-gray-500">Type</dt><dd><Badge of={CertificateType} value={cert.type} /></dd></div>
            <div><dt className="text-gray-500">Date awarded</dt><dd className="font-medium">{formatDate(cert.dateAwarded)}</dd></div>
            <div><dt className="text-gray-500">Status</dt><dd><Badge of={CertificateStatus} value={cert.status} />{verifier ? ` ${verifier}` : ''}</dd></div>
            {showId && <div><dt className="text-gray-500">National ID</dt><dd className="font-medium">{cert.idCardNo}</dd></div>}
          </dl>
          <div className="mt-4"><a href={`/certificates/verify/download?cert_number=${encodeURIComponent(cert.certNumber)}`} target="_blank" rel="noopener" className="btn-primary btn-sm">Open PDF</a></div>
        </Card>
      )}
    </>
  );
}
