import { redirect } from 'next/navigation';
import { getUser } from '@/server/session';

/** Signed-in people go to their modules; the public home page comes with the events module. */
export default async function Home() {
  redirect((await getUser()) ? '/dashboard' : '/login');
}
