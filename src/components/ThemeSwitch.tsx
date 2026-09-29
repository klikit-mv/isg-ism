'use client';

import { useEffect, useState } from 'react';

export function setTheme(mode: 'light' | 'dark' | 'system') {
  try { localStorage.setItem('scout-theme', mode); } catch { /* the choice then lasts for this page only */ }
  const dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.classList.toggle('dark', dark);
}

/** One-click light/dark toggle for the header. The choice is remembered on this device. */
export function ThemeSwitch({ className = 'rounded-lg p-1.5 hover:bg-navy-700' }: { className?: string }) {
  const [dark, setDark] = useState(false);
  useEffect(() => setDark(document.documentElement.classList.contains('dark')), []);
  const toggle = () => {
    setTheme(dark ? 'light' : 'dark');
    setDark(!dark);
  };
  const label = dark ? 'Switch to light mode' : 'Switch to dark mode';
  return (
    <button type="button" onClick={toggle} aria-label={label} title={label} data-testid="theme-switch" className={className}>
      {dark ? (
        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
      ) : (
        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
      )}
    </button>
  );
}

/** Choose light, dark or follow the device (profile page). */
export function ThemeChoice() {
  const [mode, setMode] = useState<'light' | 'dark' | 'system'>('system');
  useEffect(() => {
    try { setMode((localStorage.getItem('scout-theme') as 'light' | 'dark' | 'system') || 'system'); } catch { /* ignore */ }
  }, []);
  return (
    <div className="inline-flex overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600" role="group" aria-label="Theme">
      {(['light', 'dark', 'system'] as const).map((m) => (
        <button
          key={m}
          type="button"
          onClick={() => { setTheme(m); setMode(m); }}
          className={`px-4 py-2 text-sm capitalize ${mode === m ? 'bg-navy-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200'}`}
        >
          {m === 'system' ? 'Device' : m}
        </button>
      ))}
    </div>
  );
}
