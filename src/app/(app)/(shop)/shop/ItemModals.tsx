'use client';

import { buyAction, deleteItemAction, saveItemAction } from '@/app/actions/shop';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { FileInput, Input, Select, Textarea } from '@/components/form/fields';
import { ConfirmButton } from '@/components/ConfirmButton';
import { CancelButton, Modal, ModalButton } from '@/components/Modal';
import { RecordStatus } from '@/lib/enums';

export interface ItemView { uuid: string; name: string; description: string | null; price: string; stockQty: number; status: string }

export function ItemFormModal({ item }: { item?: ItemView }) {
  const name = item ? `edit-item-${item.uuid}` : 'add-item';
  return (
    <>
      <ModalButton name={name} className={item ? 'btn-secondary btn-sm' : 'btn-primary'}>{item ? 'Edit' : 'Add item'}</ModalButton>
      <Modal name={name} title={item ? `Edit ${item.name}` : 'Add shop item'} maxWidth="lg">
        <ActionForm action={saveItemAction} encType="multipart/form-data" closeModalOnSuccess={name}>
          {item && <input type="hidden" name="uuid" value={item.uuid} />}
          <Input name="name" label="Name" defaultValue={item?.name} required />
          <Textarea name="description" label="Description" defaultValue={item?.description} />
          <div className="grid grid-cols-2 gap-3">
            <Input name="price" label="Price (MVR)" type="number" step="0.01" min="0" defaultValue={item?.price} required />
            <Input name="stock_qty" label="Stock" type="number" min="0" defaultValue={item?.stockQty ?? 0} required />
          </div>
          {item && <Select name="status" label="Status" options={RecordStatus.options()} defaultValue={item.status} />}
          <FileInput name="image" label="Picture" accept="image/png,image/jpeg" />
          <div className="flex justify-end gap-2">
            <CancelButton name={name} />
            <SubmitButton>Save</SubmitButton>
          </div>
        </ActionForm>
      </Modal>
    </>
  );
}

export function DeleteItemButton({ uuid, name }: { uuid: string; name: string }) {
  return <ConfirmButton action={deleteItemAction} label="Remove" title="Remove item" message={`Remove ${name} from the shop? Past purchases keep their record.`} confirm="Remove" fields={{ uuid }} />;
}

export function BuyButton({ item, students, disabled }: { item: { uuid: string; name: string; price: string }; students: { uuid: string; name: string }[]; disabled?: boolean }) {
  const name = `buy-${item.uuid}`;
  if (disabled) return <button type="button" className="btn-secondary btn-sm" disabled>Out of stock</button>;
  return (
    <>
      <ModalButton name={name} className="btn-accent btn-sm">Buy</ModalButton>
      <Modal name={name} title={`Buy ${item.name}`} maxWidth="md">
        <ActionForm action={buyAction}>
          <input type="hidden" name="item" value={item.uuid} />
          <Select name="student" label="Buy for" options={students.map((s) => ({ value: s.uuid, label: s.name }))} placeholder="Choose a scout" required />
          <Input name="quantity" label="Quantity" type="number" min="1" defaultValue={1} required />
          <div className="flex justify-end gap-2">
            <CancelButton name={name} />
            <SubmitButton>Place order</SubmitButton>
          </div>
        </ActionForm>
      </Modal>
    </>
  );
}
