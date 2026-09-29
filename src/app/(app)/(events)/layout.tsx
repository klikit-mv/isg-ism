import { requireModule } from '@/server/session';

export default async function EventsLayout({ children }: { children: React.ReactNode }) {
  await requireModule('events');
  return children;
}
