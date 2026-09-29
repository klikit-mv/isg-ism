import Link from 'next/link';
import { config } from '@/lib/config';
import { FlashBanner } from '@/components/FlashBanner';
import { Logo } from '@/components/Logo';
import { ThemeSwitch } from '@/components/ThemeSwitch';
import { readFlash } from '@/server/flash';
import { getUser } from '@/server/session';
import { footerText, logoUrl } from '@/server/settings';

export const dynamic = 'force-dynamic';

/** Public pages (home, event details): a simple header with the way in. */
export default async function PublicLayout({ children }: { children: React.ReactNode }) {
  const [logo, footer, flash, user] = await Promise.all([logoUrl(), footerText(), readFlash(), getUser()]);
  return (
    <div className="flex min-h-screen flex-col bg-gray-50 font-sans text-gray-900 dark:bg-gray-900 dark:text-gray-100">
      <header className="bg-navy-900 text-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
          <Link href="/" className="flex items-center gap-3">
            <Logo url={logo} className="h-10 w-10" />
            <span className="font-semibold">{config.name}</span>
          </Link>
          <div className="flex items-center gap-2">
            <ThemeSwitch className="rounded-lg p-1.5 hover:bg-white/10" />
            {user
              ? <Link href="/dashboard" className="btn-accent btn-sm">Dashboard</Link>
              : <><Link href="/login" className="btn-accent btn-sm">Sign in</Link><Link href="/register" className="btn-secondary btn-sm">Register</Link></>}
          </div>
        </div>
      </header>
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
        <FlashBanner flash={flash} />
        {children}
      </main>
      <footer className="py-6 text-center text-xs text-gray-500">{footer}</footer>
    </div>
  );
}
