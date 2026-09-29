'use client';

import { registerScoutAction } from '@/app/actions/auth';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Input } from '@/components/form/fields';
import { StudentFields } from '@/components/StudentFields';

export function RegisterScoutForm() {
  return (
    <ActionForm action={registerScoutAction} className="space-y-6">
      <StudentFields />
      <div className="grid gap-4 sm:grid-cols-2">
        <Input name="pin" label="Choose a PIN" type="password" required help="4 to 32 characters." inputMode="numeric" />
        <Input name="pin_confirmation" label="Confirm PIN" type="password" required inputMode="numeric" />
      </div>
      <SubmitButton className="btn-primary w-full" pendingText="Registering…">Register</SubmitButton>
    </ActionForm>
  );
}
