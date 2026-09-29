import { Badge } from '@/components/Badge';
import { PageHeader } from '@/components/PageHeader';
import { Role } from '@/lib/enums';
import { requireUser } from '@/server/session';
import { PinForm, ProfileForm, AppearanceCard } from './forms';

export const metadata = { title: 'My profile' };

export default async function ProfilePage() {
  const user = await requireUser();
  return (
    <>
      <PageHeader title="My profile" description="Your details, notifications and PIN." />
      <div className="grid gap-6 lg:grid-cols-2">
        <section className="card">
          <h2 className="mb-4 text-lg font-semibold">Your details</h2>
          <div className="mb-4 grid gap-4 sm:grid-cols-2">
            <div>
              <span className="label">National ID</span>
              <p className="text-sm text-gray-600 dark:text-gray-300">{user.nationalId}</p>
            </div>
            <div>
              <span className="label">Roles</span>
              <div className="flex flex-wrap gap-1">
                {user.roles.length ? user.roles.map((r) => <Badge key={r} of={Role} value={r} />) : <span className="text-sm text-gray-500">No roles</span>}
              </div>
            </div>
          </div>
          <ProfileForm user={{ name: user.name, email: user.email, emailNotifications: user.emailNotificationsEnabled, telegramConnected: !!user.telegramChatId, telegramNotifications: user.telegramNotificationsEnabled }} />
        </section>

        <section className="card">
          <h2 className="mb-4 text-lg font-semibold">Change PIN</h2>
          <PinForm />
        </section>

        <AppearanceCard />
      </div>
    </>
  );
}
