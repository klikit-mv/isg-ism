import Link from 'next/link';

export const PAGE_SIZE = 25;

export interface Page<T> {
  rows: T[];
  total: number;
  page: number;
  pages: number;
}

export function pageOf<T>(rows: T[], total: number, page: number, size = PAGE_SIZE): Page<T> {
  return { rows, total, page, pages: Math.max(1, Math.ceil(total / size)) };
}

export const currentPage = (value: string | string[] | undefined) => Math.max(1, Number(Array.isArray(value) ? value[0] : value) || 1);

/** Previous / next links that keep the other query parameters. */
export function Pagination({ page, pages, path, query }: { page: number; pages: number; path: string; query: Record<string, string | undefined> }) {
  if (pages <= 1) return null;
  const href = (p: number) => {
    const params = new URLSearchParams();
    for (const [k, v] of Object.entries(query)) if (v) params.set(k, v);
    params.set('page', String(p));
    return `${path}?${params}`;
  };
  return (
    <nav className="mt-4 flex items-center justify-between text-sm" aria-label="Pagination">
      {page > 1 ? <Link href={href(page - 1)} className="btn-secondary btn-sm">Previous</Link> : <span />}
      <span className="text-gray-500 dark:text-gray-400">Page {page} of {pages}</span>
      {page < pages ? <Link href={href(page + 1)} className="btn-secondary btn-sm">Next</Link> : <span />}
    </nav>
  );
}
