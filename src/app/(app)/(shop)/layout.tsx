import { requireModule } from '@/server/session';

export default async function ShopLayout({ children }: { children: React.ReactNode }) {
  await requireModule('shop');
  return children;
}
