'use client';

import { issueCertificateAction } from '@/app/actions/certificates';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Input, Select } from '@/components/form/fields';

export function IssueForm({ students, templates, activities, today }: { students: { value: string; label: string }[]; templates: { value: string; label: string }[]; activities: { value: string; label: string }[]; today: string }) {
  return (
    <ActionForm action={issueCertificateAction}>
      <Select name="student" label="Scout" options={students} placeholder="Choose a scout" required />
      <Input name="title" label="Title" placeholder="Camp participation" required />
      <Input name="date_awarded" label="Date awarded" type="date" defaultValue={today} required />
      <Select name="template" label="Template" options={templates} placeholder="Choose a template" help="Not needed when you pick an activity that has its own template." />
      <Select name="activity" label="Activity (optional)" options={activities} placeholder="None" />
      <div className="flex justify-end"><SubmitButton>Issue certificate</SubmitButton></div>
    </ActionForm>
  );
}
