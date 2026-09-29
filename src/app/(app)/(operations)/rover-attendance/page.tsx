import { ActivityPicker } from '@/components/ActivityPicker';

export const metadata = { title: 'Rover attendance' };

export default async function RoverAttendancePage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  return <ActivityPicker title="Rover attendance" basePath="/rover-attendance" searchParams={await searchParams} />;
}
