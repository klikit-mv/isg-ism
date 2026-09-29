import Link from 'next/link';

export default function NotFound() {
  return (
    <div className="mx-auto max-w-md px-4 py-24 text-center">
      <p className="text-5xl font-bold text-navy-700 dark:text-navy-300">404</p>
      <h1 className="mt-4 text-xl font-semibold">Page not found</h1>
      <p className="mt-2 text-sm text-gray-500">The page may have moved, or the link is not right.</p>
      <Link href="/dashboard" className="btn-primary mt-6">Go to the portal</Link>
    </div>
  );
}
