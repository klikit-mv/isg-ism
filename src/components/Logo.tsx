import { config } from '@/lib/config';

/** The uploaded website logo, or the default emblem. */
export function Logo({ url, className = 'h-8 w-8' }: { url?: string | null; className?: string }) {
  if (url) {
    // eslint-disable-next-line @next/next/no-img-element
    return <img src={url} alt={config.name} className={`object-contain ${className}`} data-testid="site-logo" />;
  }
  return (
    <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" className={className}>
      <circle cx="24" cy="24" r="23" className="fill-navy-700" />
      <path d="M24 8c2.5 4 6 6.5 6 11.5 0 3.5-2.2 6-4.5 7.2L28 38h-8l2.5-11.3C20.2 25.5 18 23 18 19.5 18 14.5 21.5 12 24 8z" className="fill-gold-400" />
      <path d="M14 22c2 1 4 3.5 4 6.5M34 22c-2 1-4 3.5-4 6.5" stroke="#fff" strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}
