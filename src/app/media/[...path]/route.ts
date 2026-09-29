import { NextResponse } from 'next/server';
import { get, mimeFor, sniffImage } from '@/server/storage';
import { getUser } from '@/server/session';

/** Stored images (photos, badge and shop pictures) for signed-in people only. */
const ALLOWED_DIRS = ['students', 'shop-items', 'badges', 'avatars'];

export async function GET(_: Request, { params }: { params: Promise<{ path: string[] }> }) {
  if (!(await getUser())) return new NextResponse('Not found', { status: 404 });
  const { path } = await params;
  const relative = path.map(decodeURIComponent).join('/');
  if (!ALLOWED_DIRS.includes(path[0]) || relative.includes('..')) return new NextResponse('Not found', { status: 404 });
  const file = await get('public', relative);
  if (!file) return new NextResponse('Not found', { status: 404 });
  const type = sniffImage(file) ? mimeFor(relative) : 'application/octet-stream';
  return new NextResponse(new Uint8Array(file), { headers: { 'Content-Type': type, 'Cache-Control': 'private, max-age=3600', 'X-Content-Type-Options': 'nosniff' } });
}
