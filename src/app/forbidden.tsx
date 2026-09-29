import Link from 'next/link';

export default function Forbidden() {
  return (
    <div className="mx-auto max-w-md px-4 py-24 text-center">
      <p className="text-5xl font-bold text-navy-700 dark:text-navy-300">403</p>
      <h1 className="mt-4 text-xl font-semibold">You do not have access to this page</h1>
      <p className="mt-2 text-sm text-gray-500">Ask an administrator if you think this is a mistake.</p>
      <Link href="/dashboard" className="btn-primary mt-6">Go to the portal</Link>
    </div>
  );
}
