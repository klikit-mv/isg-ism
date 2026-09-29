import Link from 'next/link';
import { Empty, PageHeader } from '@/components/PageHeader';
import { ModuleIcon } from '@/components/ModuleIcon';
import { modulesFor } from '@/lib/modules';
import { requireUser } from '@/server/session';

export const metadata = { title: 'Modules' };

export default async function Dashboard() {
  const user = await requireUser();
  const modules = modulesFor(user);
  return (
    <>
      <PageHeader title={`Welcome, ${user.name}`} description="Choose where you want to work." />
      {modules.length === 0 ? (
        <Empty message="Your account does not have any modules yet. Please contact an administrator." />
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {modules.map((m) => (
            <Link key={m.key} href={m.home} data-module={m.key} className="card group flex items-start gap-4 transition hover:border-navy-300 hover:shadow-md">
              <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-navy-100 text-navy-700 group-hover:bg-navy-700 group-hover:text-white dark:bg-navy-900/60 dark:text-navy-200">
                <ModuleIcon name={m.icon} />
              </span>
              <span>
                <span className="block font-semibold text-gray-900 dark:text-gray-100">{m.title}</span>
                <span className="mt-1 block text-sm text-gray-500 dark:text-gray-400">{m.description}</span>
              </span>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}
