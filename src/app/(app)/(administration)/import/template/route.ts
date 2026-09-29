import { NextResponse } from 'next/server';
import { getUser } from '@/server/session';
import { legacyTemplate } from '@/server/legacy-import';
import { isAdmin } from '@/server/users';

export async function GET() {
  const user = await getUser();
  if (!user) return new NextResponse('Unauthorized', { status: 401 });
  if (!isAdmin(user)) return new NextResponse('Forbidden', { status: 403 });
  return new NextResponse(new Uint8Array(await legacyTemplate()), {
    headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition': 'attachment; filename="import-template.xlsx"' },
  });
}
