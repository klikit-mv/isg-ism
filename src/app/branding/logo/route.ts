import { NextResponse } from 'next/server';
import { logoPath } from '@/server/settings';
import { get, mimeFor } from '@/server/storage';

/** The website logo is public: it appears on the sign-in page. */
export async function GET() {
  const path = await logoPath();
  const file = path ? await get('public', path) : null;
  if (!path || !file) return new NextResponse('Not found', { status: 404 });
  return new NextResponse(new Uint8Array(file), { headers: { 'Content-Type': mimeFor(path), 'Cache-Control': 'public, max-age=86400', 'X-Content-Type-Options': 'nosniff' } });
}
