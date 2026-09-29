import Link from 'next/link';
import { redirect } from 'next/navigation';
import { getUser } from '@/server/session';
import { RegisterScoutForm } from './RegisterScoutForm';

export const metadata = { title: 'Register as a scout' };

export default async function RegisterPage() {
  if (await getUser()) redirect('/dashboard');
  return (
    <div>
      <h1 className="mb-1 text-xl font-bold">Register as a scout</h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">A leader will verify your registration before you can sign in.</p>
      <RegisterScoutForm />
      <p className="mt-6 text-center text-sm">Already registered? <Link href="/login" className="link">Sign in</Link></p>
    </div>
  );
}
