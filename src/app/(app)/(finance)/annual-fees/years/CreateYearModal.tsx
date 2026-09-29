'use client';

import { createYearAction } from '@/app/actions/annual-fees';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Input } from '@/components/form/fields';
import { CancelButton, Modal } from '@/components/Modal';

export function CreateYearModal({ suggested }: { suggested: number }) {
  return (
    <Modal name="create-year" title="Create fee year" maxWidth="md">
      <ActionForm action={createYearAction} closeModalOnSuccess="create-year">
        <Input name="year" label="Year" type="number" min={2000} defaultValue={suggested} required />
        <Input name="amount" label="Amount" type="number" step="0.01" min={0} required />
        <div className="flex justify-end gap-2"><CancelButton name="create-year" /><SubmitButton>Create</SubmitButton></div>
      </ActionForm>
    </Modal>
  );
}
