import Link from 'next/link';
import { redirect } from 'next/navigation';
import { getUser } from '@/server/session';
import { RegisterParentForm } from './RegisterParentForm';

export const metadata = { title: 'Register as a parent' };

export default async function RegisterParentPage() {
  if (await getUser()) redirect('/dashboard');
  return (
    <div>
      <h1 className="mb-1 text-xl font-bold">Register as a parent</h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">Add your children by National ID. A leader will verify your account before you can sign in.</p>
      <RegisterParentForm />
      <p className="mt-6 text-center text-sm">Already registered? <Link href="/login" className="link">Sign in</Link></p>
    </div>
  );
}
