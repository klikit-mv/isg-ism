import { initials } from '@/lib/format';

export function Avatar({ name, url, className = '' }: { name: string; url?: string | null; className?: string }) {
  if (url) {
    // eslint-disable-next-line @next/next/no-img-element
    return <img src={url} alt="" className={`shrink-0 rounded-full object-cover ${className}`} data-testid="user-avatar" />;
  }
  return <span className={`flex shrink-0 items-center justify-center rounded-full bg-gold-500 font-bold text-white ${className}`}>{initials(name)}</span>;
}
