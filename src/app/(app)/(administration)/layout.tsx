import { requireModule } from '@/server/session';

/** Administration is for admins only. */
export default async function AdministrationLayout({ children }: { children: React.ReactNode }) {
  await requireModule('administration');
  return children;
}
