import Link from 'next/link';
import { config } from '@/lib/config';
import { FlashBanner } from '@/components/FlashBanner';
import { Logo } from '@/components/Logo';
import { ThemeSwitch } from '@/components/ThemeSwitch';
import { readFlash } from '@/server/flash';
import { footerText, logoUrl } from '@/server/settings';

export const dynamic = 'force-dynamic';

/** Pages for people who are not signed in: a card on the brand background. */
export default async function GuestLayout({ children }: { children: React.ReactNode }) {
  const [logo, footer, flash] = await Promise.all([logoUrl(), footerText(), readFlash()]);
  return (
    <div className="min-h-screen bg-gradient-to-br from-navy-900 via-navy-800 to-navy-950 font-sans text-gray-900 dark:text-gray-100">
      <div className="flex min-h-screen flex-col items-center px-4 py-8 sm:justify-center">
        <Link href="/login" className="mb-6 flex flex-col items-center gap-2 text-white">
          <Logo url={logo} className="h-16 w-16" />
          <span className="text-lg font-semibold">{config.name}</span>
          <span className="text-sm text-navy-200">{config.shortName}</span>
        </Link>
        <div className="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800 sm:p-8 [&>*]:mx-auto">
          <FlashBanner flash={flash} />
          {children}
        </div>
        <div className="mt-6 flex flex-col items-center gap-3 text-xs text-navy-200">
          <ThemeSwitch className="rounded-lg p-1.5 hover:bg-white/10" />
          <span>{footer}</span>
        </div>
      </div>
    </div>
  );
}
