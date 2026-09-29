import { NextResponse } from 'next/server';
import { canViewCertificate, findCertificateByUuid, pdfFor } from '@/server/certificates';
import { recordAudit } from '@/server/audit';
import { getUser } from '@/server/session';

export async function GET(_: Request, { params }: { params: Promise<{ uuid: string }> }) {
  const user = await getUser();
  if (!user) return new NextResponse('Unauthorized', { status: 401 });
  const cert = await findCertificateByUuid((await params).uuid);
  if (!cert || !(await canViewCertificate(user, cert))) return new NextResponse('Not found', { status: 404 });
  const pdf = await pdfFor(cert, user);
  if (!pdf) return new NextResponse('Not found', { status: 404 });
  await recordAudit('certificate.downloaded', { type: 'certificate', id: cert.uuid }, { cert_number: cert.certNumber }, user.id);
  return new NextResponse(new Uint8Array(pdf), { headers: { 'Content-Type': 'application/pdf', 'Content-Disposition': `inline; filename="${cert.certNumber}.pdf"`, 'X-Content-Type-Options': 'nosniff' } });
}
