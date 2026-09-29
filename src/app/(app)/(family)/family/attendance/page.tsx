import { AttendanceHistoryPage } from '@/components/AttendanceHistoryPage';
import { requireUser } from '@/server/session';
import { approvedChildIds } from '@/server/users';

export const metadata = { title: 'Family attendance' };

export default async function FamilyAttendancePage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  return <AttendanceHistoryPage title="Family attendance" path="/family/attendance" studentIds={await approvedChildIds(user.id)} withChildFilter searchParams={await searchParams} />;
}
