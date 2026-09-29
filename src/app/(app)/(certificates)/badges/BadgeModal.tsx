'use client';

import { deleteBadgeAction, saveBadgeAction } from '@/app/actions/certificates';
import { ConfirmButton } from '@/components/ConfirmButton';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { FileInput, Input, Select, Textarea } from '@/components/form/fields';
import { CancelButton, Modal, ModalButton } from '@/components/Modal';
import { BADGE_CATEGORIES, ScoutSection } from '@/lib/enums';

export interface BadgeView { uuid: string; name: string; code: string; section: string | null; category: string; description: string | null; numberPrefix: string | null; templateId: number | null }

export function BadgeModal({ badge, templates }: { badge?: BadgeView; templates: { value: string; label: string }[] }) {
  const name = badge ? `edit-badge-${badge.uuid}` : 'add-badge';
  return (
    <>
      <ModalButton name={name} className={badge ? 'btn-secondary btn-sm' : 'btn-primary'}>{badge ? 'Edit' : 'Add badge'}</ModalButton>
      <Modal name={name} title={badge ? `Edit ${badge.name}` : 'Add badge'} maxWidth="lg">
        <ActionForm action={saveBadgeAction} encType="multipart/form-data" closeModalOnSuccess={name}>
          {badge && <input type="hidden" name="uuid" value={badge.uuid} />}
          <div className="grid grid-cols-2 gap-3">
            <Input name="name" label="Name" defaultValue={badge?.name} required />
            <Input name="code" label="Code" defaultValue={badge?.code} required />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Select name="section" label="Section" options={ScoutSection.options()} defaultValue={badge?.section} placeholder="Any section" />
            <Select name="category" label="Category" options={Object.entries(BADGE_CATEGORIES).map(([value, label]) => ({ value, label }))} defaultValue={badge?.category ?? 'proficiency'} />
          </div>
          <p className="-mt-2 text-xs text-gray-500">Proficiency badges of a section share one yearly numbering (SCOUT-2026-0001) that continues for the whole year.</p>
          <Textarea name="description" label="Description" rows={2} defaultValue={badge?.description} />
          <div className="grid grid-cols-2 gap-3">
            <Input name="number_prefix" label="Number prefix (other badges)" defaultValue={badge?.numberPrefix} help="Defaults to the code." />
            <Input name="next_number" label="Next number (optional)" type="number" min="1" help="Set where this year's numbering continues." />
          </div>
          <Select name="certificate_template_id" label="Certificate template" options={templates} placeholder="Use the active badge template" />
          <FileInput name="image" label="Picture" accept="image/png,image/jpeg" />
          <div className="flex justify-end gap-2"><CancelButton name={name} /><SubmitButton>Save</SubmitButton></div>
        </ActionForm>
      </Modal>
    </>
  );
}

export function DeleteBadgeButton({ uuid, name }: { uuid: string; name: string }) {
  return <ConfirmButton action={deleteBadgeAction} label="Delete" title="Delete badge" message={`Delete ${name}? A badge with requests or certificates cannot be deleted.`} confirm="Delete" fields={{ uuid }} />;
}
