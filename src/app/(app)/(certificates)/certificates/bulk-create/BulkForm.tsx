'use client';

import { bulkIssueAction } from '@/app/actions/certificates';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { CheckboxGroup, Input, Select } from '@/components/form/fields';

export function BulkForm({ students, templates, today }: { students: { value: string; label: string }[]; templates: { value: string; label: string }[]; today: string }) {
  return (
    <ActionForm action={bulkIssueAction}>
      <Input name="title" label="Title" required />
      <Input name="date_awarded" label="Date awarded" type="date" defaultValue={today} required />
      <Select name="template" label="Template" options={templates} placeholder="Choose a template" required />
      <CheckboxGroup name="students" legend="Scouts" options={students} />
      <div className="flex justify-end"><SubmitButton>Issue certificates</SubmitButton></div>
    </ActionForm>
  );
}
