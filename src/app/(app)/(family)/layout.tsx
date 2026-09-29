import { requireModule } from '@/server/session';

export default async function FamilyLayout({ children }: { children: React.ReactNode }) {
  await requireModule('family');
  return children;
}
