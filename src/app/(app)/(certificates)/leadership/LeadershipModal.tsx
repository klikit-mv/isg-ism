'use client';

import { saveLeadershipAction } from '@/app/actions/certificates';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Input, Select } from '@/components/form/fields';
import { CancelButton, Modal, ModalButton } from '@/components/Modal';

export interface LeadershipView { uuid: string; studentUuid: string; patrolOrSix: string; troopOrGroup: string; startDate: string; endDate: string | null }

export function LeadershipModal({ record, students }: { record?: LeadershipView; students: { value: string; label: string }[] }) {
  const name = record ? `edit-leadership-${record.uuid}` : 'add-leadership';
  return (
    <>
      <ModalButton name={name} className={record ? 'btn-secondary btn-sm' : 'btn-primary'}>{record ? 'Edit' : 'Add record'}</ModalButton>
      <Modal name={name} title={record ? 'Edit leadership record' : 'Add leadership record'} maxWidth="md">
        <ActionForm action={saveLeadershipAction} closeModalOnSuccess={name}>
          {record && <input type="hidden" name="uuid" value={record.uuid} />}
          <Select name="student" label="Scout" options={students} defaultValue={record?.studentUuid} placeholder="Choose a scout" required />
          <Input name="patrol_or_six" label="Patrol, six or crew" defaultValue={record?.patrolOrSix} required />
          <Input name="troop_or_group" label="Troop or group" defaultValue={record?.troopOrGroup} help="Leave empty to use the organisation name." />
          <div className="grid grid-cols-2 gap-3">
            <Input name="start_date" label="Start date" type="date" defaultValue={record?.startDate} required />
            <Input name="end_date" label="End date" type="date" defaultValue={record?.endDate} />
          </div>
          <div className="flex justify-end gap-2"><CancelButton name={name} /><SubmitButton>Save</SubmitButton></div>
        </ActionForm>
      </Modal>
    </>
  );
}
