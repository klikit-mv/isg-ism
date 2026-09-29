'use client';

import { requestBadgeAction } from '@/app/actions/certificates';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Select } from '@/components/form/fields';

export function RequestForm({ students, badges, selected }: { students: { value: string; label: string }[]; badges: { value: string; label: string }[]; selected?: string }) {
  return (
    <ActionForm action={requestBadgeAction}>
      <Select name="student" label="Scout" options={students} defaultValue={selected} placeholder="Choose a scout" required />
      <Select name="badge" label="Badge" options={badges} placeholder="Choose a badge" required />
      <div className="flex justify-end"><SubmitButton>Send request</SubmitButton></div>
    </ActionForm>
  );
}
