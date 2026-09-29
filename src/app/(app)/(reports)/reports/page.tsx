import Link from 'next/link';
import { PageHeader } from '@/components/PageHeader';
import { CATALOG } from '@/server/reports';
import { requireStaff } from '@/server/session';

export const metadata = { title: 'Reports' };

export default async function ReportsPage() {
  await requireStaff();
  return (
    <>
      <PageHeader title="Reports" description="Filter, print or download as Excel or CSV. Leaders see only their own scouts." />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {Object.entries(CATALOG).map(([type, meta]) => (
          <Link key={type} href={`/reports/${type}`} className="card block space-y-1 hover:shadow-md">
            <h2 className="font-semibold">{meta.title}</h2>
            <p className="text-sm text-gray-600 dark:text-gray-300">{meta.description}</p>
          </Link>
        ))}
      </div>
    </>
  );
}
