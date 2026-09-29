import { requireModule } from '@/server/session';

export default async function CertificatesLayout({ children }: { children: React.ReactNode }) {
  await requireModule('certificates');
  return children;
}
