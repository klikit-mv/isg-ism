import { requireModule } from '@/server/session';

/** Scout operations is for admins and leaders only. */
export default async function OperationsLayout({ children }: { children: React.ReactNode }) {
  await requireModule('operations');
  return children;
}
