import type { Metadata, Viewport } from 'next';
import './globals.css';
import { config } from '@/lib/config';

export const metadata: Metadata = {
  title: { default: config.shortName, template: `%s · ${config.shortName}` },
  description: `${config.name} portal`,
};

export const viewport: Viewport = { width: 'device-width', initialScale: 1 };

/** Applies the saved light/dark choice before first paint. */
const themeScript = `(function(){var m='system';try{m=localStorage.getItem('scout-theme')||'system'}catch(e){}var d=m==='dark'||(m!=='light'&&window.matchMedia('(prefers-color-scheme: dark)').matches);if(d){document.documentElement.classList.add('dark')}})();`;

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeScript }} />
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
      </head>
      <body className="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">{children}</body>
    </html>
  );
}
