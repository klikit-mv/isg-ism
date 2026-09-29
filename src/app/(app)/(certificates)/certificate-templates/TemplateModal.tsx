'use client';

import { saveTemplateAction } from '@/app/actions/certificates';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Checkbox, Input, Select } from '@/components/form/fields';
import { CancelButton, Modal, ModalButton } from '@/components/Modal';
import { CertificateType } from '@/lib/enums';

export interface TemplateView { uuid: string; name: string; type: string; activityUuid: string | null; active: boolean }

export function TemplateModal({ template, activities }: { template?: TemplateView; activities: { value: string; label: string }[] }) {
  const name = template ? `edit-template-${template.uuid}` : 'add-template';
  return (
    <>
      <ModalButton name={name} className={template ? 'btn-secondary btn-sm' : 'btn-primary'}>{template ? 'Edit' : 'Add template'}</ModalButton>
      <Modal name={name} title={template ? `Edit ${template.name}` : 'Add template'} maxWidth="md">
        <ActionForm action={saveTemplateAction} closeModalOnSuccess={name}>
          {template && <input type="hidden" name="uuid" value={template.uuid} />}
          <Input name="name" label="Name" defaultValue={template?.name} required />
          <Select name="type" label="Type" options={CertificateType.options()} defaultValue={template?.type ?? 'badge'} required />
          <Select name="activity" label="Activity (general templates)" options={activities} defaultValue={template?.activityUuid} placeholder="Not linked" help="Linking makes this the certificate for that activity's attendance." />
          {template && <Checkbox name="active" label="Active" defaultChecked={template.active} />}
          <div className="flex justify-end gap-2"><CancelButton name={name} /><SubmitButton>Save</SubmitButton></div>
        </ActionForm>
      </Modal>
    </>
  );
}
