import { NextResponse } from 'next/server';
import { canManageStudents } from '@/server/policies';
import { getUser } from '@/server/session';
import { importTemplate } from '@/server/student-import';

export async function GET() {
  const user = await getUser();
  if (!user) return new NextResponse('Unauthorized', { status: 401 });
  if (!canManageStudents(user)) return new NextResponse('Forbidden', { status: 403 });
  return new NextResponse(new Uint8Array(await importTemplate()), {
    headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition': 'attachment; filename="students-import-template.xlsx"' },
  });
}
