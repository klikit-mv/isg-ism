import { requireModule } from '@/server/session';

export default async function FinanceLayout({ children }: { children: React.ReactNode }) {
  await requireModule('finance');
  return children;
}
