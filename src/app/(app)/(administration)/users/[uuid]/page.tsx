import Link from 'next/link';
import { notFound } from 'next/navigation';
import { ConfirmButton } from '@/components/ConfirmButton';
import { PageHeader } from '@/components/PageHeader';
import { deleteUserAction } from '@/app/actions/users';
import { studentOptions } from '@/server/user-queries';
import { requireAdmin } from '@/server/session';
import { hasAnyRole, loadUserByUuid } from '@/server/users';
import { ResetPinForm, SignatureForm, UserForm } from '../UserForms';

export const metadata = { title: 'Edit user' };

export default async function EditUserPage({ params }: { params: Promise<{ uuid: string }> }) {
  const actor = await requireAdmin();
  const user = await loadUserByUuid((await params).uuid);
  if (!user) notFound();
  return (
    <>
      <PageHeader title={user.name} description={user.nationalId}>
        <Link href="/users" className="btn-secondary">Back to users</Link>
      </PageHeader>
      <div className="grid gap-6 lg:grid-cols-3">
        <UserForm
          students={await studentOptions()}
          user={{ uuid: user.uuid, name: user.name, nationalId: user.nationalId, email: user.email, status: user.status, studentId: user.studentId, roles: user.roles, permissions: user.permissions }}
        />
        <div className="space-y-6">
          <ResetPinForm uuid={user.uuid} />
          {hasAnyRole(user, ['admin', 'leader']) && <SignatureForm uuid={user.uuid} has={!!user.signaturePath} />}
          {user.id !== actor.id && (
            <div className="card space-y-3">
              <h2 className="font-semibold">Delete account</h2>
              <ConfirmButton action={deleteUserAction} fields={{ uuid: user.uuid }} label="Delete user" message={`Delete ${user.name}? Their history is kept, but they can no longer sign in.`} confirm="Delete" />
            </div>
          )}
        </div>
      </div>
    </>
  );
}
