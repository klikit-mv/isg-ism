import Link from 'next/link';
import { eq } from 'drizzle-orm';
import { forbidden, notFound } from 'next/navigation';
import { db, schema } from '@/db';
import { Badge } from '@/components/Badge';
import { ActionButton } from '@/components/ConfirmButton';
import { Card, PageHeader } from '@/components/PageHeader';
import { regenerateCertificateAction, signCertificateAction } from '@/app/actions/certificates';
import { formatDate, formatDateTime } from '@/lib/dates';
import { CertificateStatus, CertificateType } from '@/lib/enums';
import { canManageCertificate, canViewCertificate, findCertificateByUuid } from '@/server/certificates';
import { requireUser } from '@/server/session';

export const metadata = { title: 'Certificate' };

export default async function CertificatePage({ params }: { params: Promise<{ uuid: string }> }) {
  const user = await requireUser();
  const cert = await findCertificateByUuid((await params).uuid);
  if (!cert) notFound();
  if (!(await canViewCertificate(user, cert))) forbidden();
  const manage = await canManageCertificate(user, cert);
  const [verifier] = cert.verifiedBy ? await db.select({ name: schema.users.name }).from(schema.users).where(eq(schema.users.id, cert.verifiedBy)) : [];
  const [student] = await db.select({ uuid: schema.students.uuid }).from(schema.students).where(eq(schema.students.id, cert.studentId));
  return (
    <>
      <PageHeader title={cert.certNumber} description={cert.title || cert.badgeName}>
        <a href={`/certificates/${cert.uuid}/download`} target="_blank" rel="noopener" className="btn-primary">Open PDF</a>
        {manage && cert.status !== 'verified' && <ActionButton action={signCertificateAction} label="Verify and sign" variant="accent" size="md" fields={{ uuid: cert.uuid }} />}
        {manage && <ActionButton action={regenerateCertificateAction} label="Regenerate PDF" size="md" fields={{ uuid: cert.uuid }} />}
      </PageHeader>
      <Card className="max-w-2xl">
        <dl className="grid grid-cols-2 gap-4 text-sm">
          <div><dt className="text-gray-500">Scout</dt><dd className="font-medium">{student ? <Link href={`/students/${student.uuid}`} className="hover:underline">{cert.studentName}</Link> : cert.studentName}</dd></div>
          <div><dt className="text-gray-500">Type</dt><dd><Badge of={CertificateType} value={cert.type} /></dd></div>
          <div><dt className="text-gray-500">Awarded</dt><dd className="font-medium">{formatDate(cert.dateAwarded)}</dd></div>
          <div><dt className="text-gray-500">Status</dt><dd><Badge of={CertificateStatus} value={cert.status} /></dd></div>
          {verifier && <div className="col-span-2"><dt className="text-gray-500">Verified by</dt><dd className="font-medium">{verifier.name}{cert.verifiedAt ? ` on ${formatDateTime(cert.verifiedAt)}` : ''}</dd></div>}
          <div className="col-span-2"><dt className="text-gray-500">Check this number</dt><dd><Link href={`/certificates/verify?cert_number=${encodeURIComponent(cert.certNumber)}`} className="link">Verification page</Link></dd></div>
        </dl>
      </Card>
    </>
  );
}
