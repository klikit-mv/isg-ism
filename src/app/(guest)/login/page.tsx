import Link from 'next/link';
import { eq } from 'drizzle-orm';
import { redirect } from 'next/navigation';
import { config } from '@/lib/config';
import { db, schema } from '@/db';
import { getUser } from '@/server/session';
import { DEMO_ACCOUNTS, demoPin } from '@/server/demo-accounts';
import { LoginForm } from './LoginForm';
import { safeNext } from '@/lib/safe-next';

export const metadata = { title: 'Sign in' };

export default async function LoginPage({ searchParams }: { searchParams: Promise<{ next?: string }> }) {
  if (await getUser()) redirect('/dashboard');
  const { next } = await searchParams;

  let showDemo = false;
  if (config.showDemoLogins) {
    const [row] = await db.select({ id: schema.users.id }).from(schema.users).where(eq(schema.users.nationalId, DEMO_ACCOUNTS[0].nationalId)).limit(1);
    showDemo = !!row;
  }

  return (
    <div className="max-w-md">
      <h1 className="mb-1 text-xl font-bold">Sign in</h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">Use your National ID and PIN.</p>
      <LoginForm next={next ? safeNext(next) : ''} demo={showDemo ? { accounts: DEMO_ACCOUNTS, pin: demoPin() } : null} />
      <div className="mt-6 space-y-2 border-t border-gray-100 pt-4 text-center text-sm dark:border-gray-700">
        <p>New scout? <Link href="/register" className="link">Register as a scout</Link></p>
        <p>Parent? <Link href="/register/parent" className="link">Register as a parent</Link></p>
        <p><Link href="/" className="link">See upcoming events</Link></p>
        <p><Link href="/certificates/verify" className="link">Verify a certificate</Link></p>
      </div>
    </div>
  );
}
