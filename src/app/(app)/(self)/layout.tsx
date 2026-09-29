import { requireModule } from '@/server/session';

export default async function SelfLayout({ children }: { children: React.ReactNode }) {
  await requireModule('self');
  return children;
}
