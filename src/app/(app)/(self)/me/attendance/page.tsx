import { AttendanceHistoryPage } from '@/components/AttendanceHistoryPage';
import { requireUser } from '@/server/session';

export const metadata = { title: 'My attendance' };

export default async function SelfAttendancePage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  return <AttendanceHistoryPage title="My attendance" path="/me/attendance" studentIds={user.studentId ? [user.studentId] : []} withChildFilter={false} searchParams={await searchParams} />;
}
