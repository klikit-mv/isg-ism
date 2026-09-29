'use client';

import { createUserAction, resetPinAction, updateUserAction, uploadSignatureAction } from '@/app/actions/users';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { FileInput, Input, Select } from '@/components/form/fields';
import { Permission, Role, UserStatus, roleDescriptions, type RoleValue } from '@/lib/enums';
import { useFormState } from '@/components/form/ActionForm';

export interface UserDefaults {
  uuid: string;
  name: string;
  nationalId: string;
  email: string | null;
  status: string;
  studentId: number | null;
  roles: string[];
  permissions: string[];
}

function Checks({ name, values, chosen, describe }: { name: string; values: { value: string; label: string; description?: string }[]; chosen: string[]; describe?: boolean }) {
  const state = useFormState();
  const typed = state?.values?.[name];
  const current = Array.isArray(typed) ? (typed as string[]) : chosen;
  return (
    <div className={describe ? 'grid gap-2 sm:grid-cols-2' : 'grid gap-2 sm:grid-cols-2'}>
      {values.map((v) => (
        <label key={v.value} className={describe ? 'flex items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700' : 'flex items-center gap-2 text-sm'}>
          <input type="checkbox" name={`${name}[]`} value={v.value} defaultChecked={current.includes(v.value)} key={JSON.stringify(current)} className="mt-0.5 rounded border-gray-300 text-navy-600" />
          <span>
            <span className={describe ? 'font-medium' : ''}>{v.label}</span>
            {v.description && <span className="block text-xs text-gray-500">{v.description}</span>}
          </span>
        </label>
      ))}
    </div>
  );
}

export function UserForm({ user, students }: { user?: UserDefaults; students: { value: string; label: string }[] }) {
  const editing = !!user;
  return (
    <ActionForm action={editing ? updateUserAction : createUserAction} className="card space-y-4 lg:col-span-2">
      {editing && <input type="hidden" name="uuid" value={user.uuid} />}
      <div className="grid gap-4 sm:grid-cols-2">
        <Input name="name" label="Name" defaultValue={user?.name} required />
        {editing ? <div><span className="label">National ID</span><p className="py-2 text-sm">{user.nationalId}</p></div> : <Input name="national_id" label="National ID" required className="input uppercase" />}
        <Input name="email" label="Email" type="email" defaultValue={user?.email} required={!editing} />
        {editing ? <Select name="status" label="Status" options={UserStatus.options()} defaultValue={user.status} /> : <Input name="pin" label="PIN" type="password" required help="At least 4 characters." />}
        <Select name="student_id" label="Linked scout (optional)" options={students} defaultValue={user?.studentId ? String(user.studentId) : ''} placeholder="None" />
      </div>
      <fieldset>
        <legend className="label">Roles</legend>
        <Checks name="roles" describe chosen={user?.roles ?? []} values={Role.options().map((o) => ({ value: o.value, label: o.label, description: roleDescriptions[o.value as RoleValue] }))} />
      </fieldset>
      <fieldset>
        <legend className="label">Extra permissions</legend>
        <Checks name="permissions" chosen={user?.permissions ?? []} values={Permission.options()} />
      </fieldset>
      <SubmitButton>{editing ? 'Save changes' : 'Create user'}</SubmitButton>
    </ActionForm>
  );
}

export function ResetPinForm({ uuid }: { uuid: string }) {
  return (
    <ActionForm action={resetPinAction} className="card space-y-3">
      <input type="hidden" name="uuid" value={uuid} />
      <h2 className="font-semibold">Reset PIN</h2>
      <p className="text-sm text-gray-500">This signs the user out on every device.</p>
      <Input name="pin" label="New PIN" type="password" required />
      <SubmitButton className="btn-primary btn-sm">Reset PIN</SubmitButton>
    </ActionForm>
  );
}

export function SignatureForm({ uuid, has }: { uuid: string; has: boolean }) {
  return (
    <ActionForm action={uploadSignatureAction} className="card space-y-3" encType="multipart/form-data">
      <input type="hidden" name="uuid" value={uuid} />
      <h2 className="font-semibold">Signature</h2>
      {has && <p className="text-sm text-emerald-700 dark:text-emerald-300">A signature is on file.</p>}
      <FileInput name="signature" accept="image/png,image/jpeg" help="PNG or JPEG, up to 2 MB." />
      <SubmitButton className="btn-primary btn-sm">Upload</SubmitButton>
    </ActionForm>
  );
}
