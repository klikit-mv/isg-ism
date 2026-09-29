import { config } from '@/lib/config';
import { MODULES, canOpen, navFor, type NavLink } from '@/lib/modules';
import { FlashBanner } from '@/components/FlashBanner';
import { AppShell } from '@/components/AppShell';
import { logoUrl, footerText } from '@/server/settings';
import { readFlash } from '@/server/flash';
import { unread, unreadCount } from '@/server/notifications';
import { requireUser } from '@/server/session';
import { logoutAction } from '@/app/actions/auth';
import { avatarUrl } from '@/server/media';

export const dynamic = 'force-dynamic';

export default async function AppLayout({ children }: { children: React.ReactNode }) {
  const user = await requireUser();
  const [logo, footer, alerts, count, flash, avatar] = await Promise.all([
    logoUrl(), footerText(), unread(user.id), unreadCount(user.id), readFlash(), avatarUrl(user),
  ]);

  const navByModule: Record<string, NavLink[]> = {};
  for (const module of MODULES) if (canOpen(user, module)) navByModule[module.key] = navFor(user, module);

  return (
    <AppShell
      user={{ name: user.name, nationalId: user.nationalId, avatarUrl: avatar }}
      shortName={config.shortName}
      logoUrl={logo}
      footer={footer}
      notifications={alerts.map((n) => ({ id: n.id, title: n.data.title, body: n.data.body }))}
      unreadCount={count}
      navByModule={navByModule}
      logout={logoutAction}
      flash={<FlashBanner flash={flash} />}
    >
      {children}
    </AppShell>
  );
}
