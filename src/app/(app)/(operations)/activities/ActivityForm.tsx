'use client';

import { useState } from 'react';
import { createActivityAction, updateActivityAction } from '@/app/actions/activities';
import { ActionForm, SubmitButton, useFormState } from '@/components/form/ActionForm';
import { Input, Select, Textarea } from '@/components/form/fields';
import { MultiPick, type PickOption } from '@/components/form/MultiPick';
import { CancelButton } from '@/components/Modal';
import { ScoutSection } from '@/lib/enums';

export interface ActivityDefaults {
  uuid: string;
  name: string;
  date: string;
  details: string | null;
  allStudents: boolean;
  chargeFee: boolean;
  feeAmount: string | null;
  certificateTemplateId: number | null;
  sections: string[];
  groups: number[];
}

function Fields({ activity, groupOptions, templateOptions }: { activity?: ActivityDefaults; groupOptions: PickOption[]; templateOptions: { value: string; label: string }[] }) {
  const state = useFormState();
  const [all, setAll] = useState(activity?.allStudents ?? false);
  const [charge, setCharge] = useState(activity?.chargeFee ?? false);
  const typedSections = state?.values?.sections;
  const sections = Array.isArray(typedSections) ? (typedSections as string[]) : activity?.sections ?? [];
  return (
    <div className="space-y-4">
      <Input name="name" label="Name" defaultValue={activity?.name} required />
      <div className="grid gap-4 sm:grid-cols-2">
        <Input name="date" label="Date" type="date" defaultValue={activity?.date ?? new Date().toISOString().slice(0, 10)} required />
        <Select name="certificate_template_id" label="Certificate template (optional)" options={templateOptions} defaultValue={activity?.certificateTemplateId ? String(activity.certificateTemplateId) : ''} placeholder="None" />
      </div>
      <Textarea name="details" label="Details" defaultValue={activity?.details} />

      <fieldset className="space-y-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
        <legend className="px-1 text-sm font-medium">Who is expected</legend>
        <input type="hidden" name="all_students" value={all ? '1' : '0'} />
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={all} onChange={(e) => setAll(e.target.checked)} className="rounded border-gray-300 text-navy-600" /> All scouts</label>
        <div className={all ? 'hidden' : 'space-y-3'}>
          <div>
            <span className="label">Sections</span>
            <div className="flex flex-wrap gap-3">
              {ScoutSection.values.map((s) => (
                <label key={s} className="flex items-center gap-2 text-sm">
                  <input type="checkbox" name="sections[]" value={s} defaultChecked={sections.includes(s)} className="rounded border-gray-300 text-navy-600" /> {s}
                </label>
              ))}
            </div>
            {state?.fields?.sections && <p className="mt-1 text-xs text-rose-600">{state.fields.sections}</p>}
          </div>
          <MultiPick name="groups" label="Groups" options={groupOptions} selected={activity?.groups ?? []} placeholder="Search groups" />
        </div>
      </fieldset>

      <div className="space-y-3">
        <input type="hidden" name="charge_fee" value={charge ? '1' : '0'} />
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={charge} onChange={(e) => setCharge(e.target.checked)} className="rounded border-gray-300 text-navy-600" /> Charge a class fee</label>
        {charge && <Input name="fee_amount" label="Fee amount" type="number" step="0.01" min="0" defaultValue={activity?.feeAmount} help="Leave blank to use the default class fee." />}
      </div>
    </div>
  );
}

export function ActivityForm({ activity, groupOptions, templateOptions, modal }: { activity?: ActivityDefaults; groupOptions: PickOption[]; templateOptions: { value: string; label: string }[]; modal?: string }) {
  return (
    <ActionForm action={activity ? updateActivityAction : createActivityAction} className={activity ? 'card space-y-4' : 'space-y-4'} closeModalOnSuccess={modal}>
      {activity && <input type="hidden" name="uuid" value={activity.uuid} />}
      <Fields activity={activity} groupOptions={groupOptions} templateOptions={templateOptions} />
      <div className="flex justify-end gap-2">
        {modal && <CancelButton name={modal} />}
        <SubmitButton>{activity ? 'Save changes' : 'Create'}</SubmitButton>
      </div>
    </ActionForm>
  );
}
