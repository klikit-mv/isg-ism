'use client';

import { saveSettingsAction } from '@/app/actions/settings';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Checkbox, FileInput, Input, Textarea } from '@/components/form/fields';
import { Logo } from '@/components/Logo';

interface Values {
  fee: string;
  bank: string | null;
  accountName: string | null;
  accountNumber: string | null;
  instructions: string | null;
  maxKb: number;
  mimes: string;
  shop: boolean;
  footer: string;
  hasLogo: boolean;
  logo: string | null;
}

export function SettingsForm({ values: v }: { values: Values }) {
  return (
    <ActionForm action={saveSettingsAction} className="grid gap-6 lg:grid-cols-2" encType="multipart/form-data">
      <section className="card space-y-4">
        <h2 className="font-semibold">Fees and payments</h2>
        <Input name="default_class_fee" label="Default class fee" type="number" step="0.01" min="0" defaultValue={v.fee} required />
        <Input name="bank_name" label="Bank name" defaultValue={v.bank} />
        <Input name="account_name" label="Account name" defaultValue={v.accountName} />
        <Input name="account_number" label="Account number" defaultValue={v.accountNumber} />
        <Textarea name="payment_instructions" label="Payment instructions" defaultValue={v.instructions} />
        <Input name="proof_max_kb" label="Largest proof file (KB)" type="number" min="100" max="20480" defaultValue={v.maxKb} required help={`Accepted types: ${v.mimes}.`} />
      </section>

      <section className="space-y-6">
        <div className="card space-y-4">
          <h2 className="font-semibold">Website logo</h2>
          <div className="flex items-center gap-4">
            <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-navy-800 p-1"><Logo url={v.logo} className="h-14 w-14" /></div>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {v.hasLogo ? 'Your uploaded logo is in use.' : 'The built-in logo is in use.'} Shown in the header, on the sign-in page, as the browser icon and on certificates.
            </p>
          </div>
          <FileInput name="logo" label="Upload a new logo" accept="image/png,image/jpeg" help="PNG or JPEG, up to 2 MB. A square image with a transparent background works best." />
          {v.hasLogo && <Checkbox name="remove_logo" label="Remove the uploaded logo and use the built-in one" />}
        </div>

        <div className="card space-y-4">
          <h2 className="font-semibold">Shop and portal</h2>
          <Checkbox name="shop_enabled" label="The shop is open" defaultChecked={v.shop} />
          <Input name="footer_text" label="Footer text" defaultValue={v.footer} />
        </div>
      </section>

      <div className="lg:col-span-2"><SubmitButton>Save settings</SubmitButton></div>
    </ActionForm>
  );
}
