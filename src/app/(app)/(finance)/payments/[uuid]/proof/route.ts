import { NextResponse } from 'next/server';
import { eq } from 'drizzle-orm';
import { db, schema } from '@/db';
import { canViewPayment, findPaymentByUuid } from '@/server/finance-queries';
import { getUser } from '@/server/session';
import { get } from '@/server/storage';

/** A payment proof, only for people who can see the payment. Never cached. */
export async function GET(_: Request, { params }: { params: Promise<{ uuid: string }> }) {
  const user = await getUser();
  if (!user) return new NextResponse('Not found', { status: 404 });
  const payment = await findPaymentByUuid((await params).uuid);
  if (!payment || !(await canViewPayment(user, payment))) return new NextResponse('Not found', { status: 404 });
  const [proof] = await db.select().from(schema.paymentProofs).where(eq(schema.paymentProofs.paymentId, payment.id)).limit(1);
  const file = proof ? await get(proof.disk === 'local' ? 'local' : 'public', proof.path) : null;
  if (!proof || !file) return new NextResponse('Not found', { status: 404 });
  return new NextResponse(new Uint8Array(file), {
    headers: {
      'Content-Type': proof.mimeType || 'application/octet-stream',
      'Content-Disposition': `inline; filename="${(proof.originalFilename || 'proof').replace(/[^\w.\- ]/g, '_')}"`,
      'X-Content-Type-Options': 'nosniff',
      'Cache-Control': 'private, no-store',
    },
  });
}
