import { requireModule } from '@/server/session';

export default async function ReportsLayout({ children }: { children: React.ReactNode }) {
  await requireModule('reports');
  return children;
}
