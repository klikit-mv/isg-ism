'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useState } from 'react';
import { activeLink, isPersonalPath, moduleForPath, PERSONAL_NAV, type NavLink } from '@/lib/modules';
import { Avatar } from './Avatar';
import { Logo } from './Logo';
import { ThemeSwitch } from './ThemeSwitch';

export interface ShellNotification {
  id: string;
  title: string;
  body: string;
}

export interface ShellProps {
  user: { name: string; nationalId: string; avatarUrl: string | null };
  shortName: string;
  logoUrl: string | null;
  footer: string;
  notifications: ShellNotification[];
  unreadCount: number;
  /** Sidebar links per module the person may open. */
  navByModule: Record<string, NavLink[]>;
  logout: () => void | Promise<void>;
  flash: React.ReactNode;
  children: React.ReactNode;
}

const personalLinks: NavLink[] = PERSONAL_NAV.map((n) => ({ label: n.label, href: n.href, match: n.match ?? [n.href], exact: false }));

export function AppShell({ user, shortName, logoUrl, footer, notifications, unreadCount, navByModule, logout, flash, children }: ShellProps) {
  const pathname = usePathname();
  const [sidebar, setSidebar] = useState(false);
  const [bell, setBell] = useState(false);
  const [menu, setMenu] = useState(false);
  const [expanded, setExpanded] = useState<string | null>(null);

  const personal = isPersonalPath(pathname);
  const module = personal ? null : moduleForPath(pathname);
  const links = personal ? personalLinks : module ? navByModule[module.key] ?? [] : [];
  const active = activeLink(links, pathname);
  const title = personal ? 'My account' : module?.title ?? null;

  return (
    <div className="flex min-h-screen flex-col">
      <header className="sticky top-0 z-40 border-b border-navy-900 bg-navy-800 text-white dark:bg-navy-950">
        <div className="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4">
          {links.length > 0 && (
            <button type="button" className="rounded p-1 hover:bg-navy-700 md:hidden" onClick={() => setSidebar(!sidebar)} aria-label="Menu">
              <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
          )}
          <Link href="/dashboard" className="flex items-center gap-2 font-semibold">
            <Logo url={logoUrl} className="h-8 w-8" />
            <span className="hidden sm:inline">{shortName}</span>
          </Link>
          {title && (
            <>
              <span className="hidden text-navy-300 sm:inline">/</span>
              <span className="truncate text-sm text-navy-100">{title}</span>
            </>
          )}

          <div className="ml-auto flex items-center gap-2">
            <Link href="/dashboard" className="hidden rounded-lg px-3 py-1.5 text-sm hover:bg-navy-700 sm:inline-block">Modules</Link>
            <ThemeSwitch />

            <div className="relative">
              <button type="button" onClick={() => { setBell(!bell); setMenu(false); }} className="relative rounded-lg p-1.5 hover:bg-navy-700" aria-label="Notifications">
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8"><path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                {unreadCount > 0 && <span className="absolute -right-0.5 -top-0.5 rounded-full bg-gold-500 px-1.5 text-[10px] font-bold" data-testid="unread-count">{unreadCount}</span>}
              </button>
              {bell && (
                <>
                  <div className="fixed inset-0 z-40" onClick={() => setBell(false)} />
                  <div className="absolute right-0 z-50 mt-2 w-80 max-w-[90vw] overflow-hidden rounded-xl bg-white text-gray-800 shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:text-gray-100">
                    <div className="border-b border-gray-100 px-4 py-2 text-sm font-semibold dark:border-gray-700">Notifications</div>
                    <div className="max-h-96 overflow-y-auto">
                      {notifications.length === 0 && <div className="px-4 py-6 text-center text-sm text-gray-500">You are all caught up.</div>}
                      {notifications.map((n) => (
                        <div key={n.id} className="border-b border-gray-100 px-4 py-3 text-sm dark:border-gray-700">
                          <button type="button" className="w-full text-left" onClick={() => setExpanded(expanded === n.id ? null : n.id)}>
                            <div className="font-medium">{n.title}</div>
                            <div className={`text-gray-500 dark:text-gray-400 ${expanded === n.id ? '' : 'truncate'}`}>{n.body}</div>
                          </button>
                          {expanded === n.id && <Link href={`/notifications/${n.id}`} onClick={() => setBell(false)} className="link mt-1 inline-block text-xs">Open full details</Link>}
                        </div>
                      ))}
                    </div>
                    <Link href="/notifications" onClick={() => setBell(false)} className="block bg-gray-50 px-4 py-2 text-center text-sm font-medium text-navy-700 dark:bg-gray-900 dark:text-navy-300">See all notifications</Link>
                  </div>
                </>
              )}
            </div>

            <div className="relative">
              <button type="button" onClick={() => { setMenu(!menu); setBell(false); }} className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-navy-700" data-testid="user-menu">
                <Avatar name={user.name} url={user.avatarUrl} className="h-7 w-7 text-xs" />
                <span className="hidden max-w-[10rem] truncate md:inline">{user.name}</span>
              </button>
              {menu && (
                <>
                  <div className="fixed inset-0 z-40" onClick={() => setMenu(false)} />
                  <div className="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl bg-white py-1 text-sm text-gray-800 shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:text-gray-100">
                    <div className="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">{user.nationalId}</div>
                    <Link href="/dashboard" onClick={() => setMenu(false)} className="block px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700">Modules</Link>
                    <Link href="/profile" onClick={() => setMenu(false)} className="block px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700">Profile</Link>
                    <form action={logout}>
                      <button type="submit" className="block w-full px-4 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700">Sign out</button>
                    </form>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      </header>

      <div className="mx-auto flex w-full max-w-7xl flex-1">
        {links.length > 0 && (
          <>
            <aside className={`fixed inset-y-14 left-0 z-30 w-60 overflow-y-auto border-r border-gray-200 bg-white p-3 transition md:static md:inset-auto md:translate-x-0 md:bg-transparent dark:border-gray-800 dark:bg-gray-900 md:dark:bg-transparent ${sidebar ? 'translate-x-0' : '-translate-x-full'}`}>
              {title && <div className="mb-2 px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{title}</div>}
              <nav className="space-y-1">
                {links.map((link) => (
                  <Link
                    key={link.href}
                    href={link.href}
                    onClick={() => setSidebar(false)}
                    className={`block rounded-lg px-3 py-2 text-sm font-medium ${active?.href === link.href ? 'bg-navy-100 text-navy-800 dark:bg-navy-900/60 dark:text-navy-100' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'}`}
                  >
                    {link.label}
                  </Link>
                ))}
              </nav>
            </aside>
            {sidebar && <div onClick={() => setSidebar(false)} className="fixed inset-0 top-14 z-20 bg-black/30 md:hidden" />}
          </>
        )}
        <main className="min-w-0 flex-1 px-4 py-6 sm:px-6">
          {flash}
          {children}
        </main>
      </div>

      <footer className="border-t border-gray-200 py-4 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">{footer}</footer>
    </div>
  );
}
