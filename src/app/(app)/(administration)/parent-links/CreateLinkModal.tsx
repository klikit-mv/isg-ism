'use client';

import { createLinkAction } from '@/app/actions/parent-links';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Select } from '@/components/form/fields';
import { CancelButton, Modal } from '@/components/Modal';
import { ParentLinkStatus } from '@/lib/enums';

export function CreateLinkModal({ parents, students }: { parents: { value: string; label: string }[]; students: { value: string; label: string }[] }) {
  return (
    <Modal name="create-link" title="Link a parent to a scout" maxWidth="lg">
      <ActionForm action={createLinkAction} closeModalOnSuccess="create-link">
        <Select name="parent_user_id" label="Parent account" options={parents} placeholder="Choose a user" required />
        <Select name="student_id" label="Scout" options={students} placeholder="Choose a scout" required />
        <Select name="status" label="Status" options={ParentLinkStatus.options()} defaultValue="approved" />
        <p className="text-xs text-gray-500">The parent role is added to the account if missing.</p>
        <div className="flex justify-end gap-2">
          <CancelButton name="create-link" />
          <SubmitButton>Save link</SubmitButton>
        </div>
      </ActionForm>
    </Modal>
  );
}
