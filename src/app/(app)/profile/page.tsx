import { Badge } from '@/components/Badge';
import { PageHeader } from '@/components/PageHeader';
import { Role } from '@/lib/enums';
import { requireUser } from '@/server/session';
import { isStaff } from '@/server/users';
import { avatarUrl } from '@/server/media';
import { Avatar } from '@/components/Avatar';
import { ActionButton } from '@/components/ConfirmButton';
import { PhotoUpload } from '@/components/PhotoUpload';
import { avatarAction, ownSignatureAction } from '@/app/actions/profile';
import { PinForm, ProfileForm, AppearanceCard } from './forms';

export const metadata = { title: 'My profile' };

export default async function ProfilePage() {
  const user = await requireUser();
  const picture = await avatarUrl(user);
  return (
    <>
      <PageHeader title="My profile" description="Your details, notifications and PIN." />
      <div className="grid gap-6 lg:grid-cols-2">
        <section className="card" data-testid="profile-picture">
          <h2 className="mb-4 text-lg font-semibold">Profile picture</h2>
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
            <Avatar name={user.name} url={picture} className="h-24 w-24 text-2xl" />
            <div className="flex-1 space-y-3">
              <PhotoUpload action={avatarAction} name="avatar" label="Upload picture" help="PNG, JPEG or WebP, up to 2 MB. A square picture looks best." />
              {user.avatarPath && <ActionButton action={avatarAction} label="Remove picture" fields={{ remove: '1' }} />}
            </div>
          </div>
        </section>

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

        {isStaff(user) && (
          <section className="card">
            <h2 className="mb-2 text-lg font-semibold">Signature</h2>
            <p className="mb-3 text-sm text-gray-500 dark:text-gray-400">Used when you sign certificates. PNG or JPEG, up to 2 MB.</p>
            {user.signaturePath && <p className="mb-3 text-sm text-emerald-700 dark:text-emerald-300">A signature is on file.</p>}
            <PhotoUpload action={ownSignatureAction} name="signature" label="Upload signature" help="PNG or JPEG, up to 2 MB." />
          </section>
        )}
      </div>
    </>
  );
}
