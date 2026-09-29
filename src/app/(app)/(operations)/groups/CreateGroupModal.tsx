'use client';

import { createGroupAction } from '@/app/actions/groups';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Input, Select } from '@/components/form/fields';
import { CancelButton, Modal } from '@/components/Modal';
import { ScoutSection } from '@/lib/enums';

export function CreateGroupModal() {
  return (
    <Modal name="create-group" title="Create group" maxWidth="md">
      <ActionForm action={createGroupAction}>
        <Input name="name" label="Name" required />
        <Input name="type" label="Type" placeholder="Patrol, Six, Crew…" />
        <Select name="section" label="Section" options={ScoutSection.options()} placeholder="Mixed (any section)" />
        <p className="-mt-2 text-xs text-gray-500">Only scouts from this section can be added as members.</p>
        <p className="text-xs text-gray-500">You become the owner and first leader.</p>
        <div className="flex justify-end gap-2">
          <CancelButton name="create-group" />
          <SubmitButton>Create</SubmitButton>
        </div>
      </ActionForm>
    </Modal>
  );
}
