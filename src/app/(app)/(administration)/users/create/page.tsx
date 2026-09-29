import Link from 'next/link';
import { PageHeader } from '@/components/PageHeader';
import { studentOptions } from '@/server/user-queries';
import { UserForm } from '../UserForms';

export const metadata = { title: 'Create user' };

export default async function CreateUserPage() {
  return (
    <>
      <PageHeader title="Create user" description="A new account with roles and permissions.">
        <Link href="/users" className="btn-secondary">Back to users</Link>
      </PageHeader>
      <div className="grid gap-6 lg:grid-cols-3"><UserForm students={await studentOptions()} /></div>
    </>
  );
}
