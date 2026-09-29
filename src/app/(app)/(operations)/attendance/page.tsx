import { ActivityPicker } from '@/components/ActivityPicker';

export const metadata = { title: 'Mark attendance' };

export default async function AttendancePage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  return <ActivityPicker title="Mark attendance" basePath="/attendance" searchParams={await searchParams} />;
}
