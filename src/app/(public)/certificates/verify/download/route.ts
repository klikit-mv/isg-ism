import { NextResponse } from 'next/server';
import { findCertificateByNumber, pdfFor } from '@/server/certificates';
import { hit, tooManyAttempts } from '@/server/ratelimit';
import { clientIp } from '@/server/session';

export async function GET(request: Request) {
  const ip = await clientIp();
  const key = `certificate-verify:${ip}`;
  if (await tooManyAttempts(key, 30)) return new NextResponse('Too many requests', { status: 429 });
  await hit(key, 60);
  const cert = await findCertificateByNumber(new URL(request.url).searchParams.get('cert_number') ?? '');
  if (!cert) return new NextResponse('Not found', { status: 404 });
  const pdf = await pdfFor(cert, null);
  if (!pdf) return new NextResponse('Not found', { status: 404 });
  return new NextResponse(new Uint8Array(pdf), { headers: { 'Content-Type': 'application/pdf', 'Content-Disposition': `inline; filename="${cert.certNumber}.pdf"`, 'X-Content-Type-Options': 'nosniff' } });
}
