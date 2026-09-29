'use client';

import { membershipAction, updateGroupAction } from '@/app/actions/groups';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Input, Select } from '@/components/form/fields';
import { MultiPick, type PickOption } from '@/components/form/MultiPick';
import { ScoutSection } from '@/lib/enums';

export function GroupForms({ group, selected, memberOptions, leaderOptions, roverOptions }: {
  group: { uuid: string; name: string; type: string | null; section: string | null };
  selected: { members: number[]; leaders: number[]; assistants: number[] };
  memberOptions: PickOption[];
  leaderOptions: PickOption[];
  roverOptions: PickOption[];
}) {
  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <ActionForm action={updateGroupAction} className="card space-y-4">
        <input type="hidden" name="uuid" value={group.uuid} />
        <h2 className="font-semibold">Details</h2>
        <Input name="name" label="Name" defaultValue={group.name} required />
        <Input name="type" label="Type" defaultValue={group.type} />
        <Select name="section" label="Section" options={ScoutSection.options()} defaultValue={group.section} placeholder="Mixed (any section)" />
        <SubmitButton className="btn-primary btn-sm">Save</SubmitButton>
      </ActionForm>

      <ActionForm action={membershipAction} className="card space-y-6 lg:col-span-2">
        <input type="hidden" name="uuid" value={group.uuid} />
        <h2 className="font-semibold">Membership</h2>
        {group.section && <p className="text-sm text-gray-500 dark:text-gray-400">Showing {group.section} scouts only.</p>}
        <MultiPick name="members" label="Members" options={memberOptions} selected={selected.members} placeholder="Search scouts" />
        <MultiPick name="leaders" label="Leaders" options={leaderOptions} selected={selected.leaders} placeholder="Search leaders" />
        <MultiPick name="assistant_leaders" label="Rover assistant leaders" options={roverOptions} selected={selected.assistants} placeholder="Search Rovers" />
        <SubmitButton>Save membership</SubmitButton>
      </ActionForm>
    </div>
  );
}
