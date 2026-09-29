'use client';

import { deleteEventItemAction, saveEventItemAction } from '@/app/actions/events';
import { ConfirmButton } from '@/components/ConfirmButton';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Checkbox, Input, Textarea } from '@/components/form/fields';
import { CancelButton, Modal, ModalButton } from '@/components/Modal';

export interface EventItemView {
  uuid: string;
  name: string;
  description: string | null;
  price: string;
  sizesText: string;
  sizeGuide: string | null;
  stock: number | null;
  maxPerRegistration: number;
  active: boolean;
}

export function EventItemModal({ eventUuid, item }: { eventUuid: string; item?: EventItemView }) {
  const name = item ? `edit-event-item-${item.uuid}` : 'add-event-item';
  return (
    <>
      <ModalButton name={name} className={item ? 'btn-secondary btn-sm' : 'btn-primary btn-sm'}>{item ? 'Edit' : 'Add item'}</ModalButton>
      <Modal name={name} title={item ? `Edit ${item.name}` : 'Add pre-order item'} maxWidth="lg">
        <ActionForm action={saveEventItemAction} closeModalOnSuccess={name}>
          <input type="hidden" name="event" value={eventUuid} />
          {item && <input type="hidden" name="item" value={item.uuid} />}
          <Input name="name" label="Item" placeholder="Event T-shirt" defaultValue={item?.name} required />
          <Textarea name="description" label="Description" rows={2} defaultValue={item?.description} />
          <div className="grid grid-cols-3 gap-3">
            <Input name="price" label="Price (MVR)" type="number" step="0.01" min="0" defaultValue={item?.price ?? '0.00'} required />
            <Input name="stock" label="Stock (optional)" type="number" min="0" defaultValue={item?.stock} />
            <Input name="max_per_registration" label="Max per person" type="number" min="1" defaultValue={item?.maxPerRegistration ?? 5} required />
          </div>
          <Textarea
            name="sizes" label="Sizes" rows={5} defaultValue={item?.sizesText}
            help={'Leave empty if the item has no sizes. Either "S, M, L" or one size per line with measurements, e.g. "M: Chest 38 in, Length 28 in".'}
          />
          <Textarea name="size_guide" label="Size guide (shown to members)" rows={2} defaultValue={item?.sizeGuide} help="Extra note such as how to measure." />
          {item && <Checkbox name="active" label="Available to order" defaultChecked={item.active} />}
          <div className="flex justify-end gap-2">
            <CancelButton name={name} />
            <SubmitButton>Save</SubmitButton>
          </div>
        </ActionForm>
      </Modal>
    </>
  );
}

export function DeleteEventItemButton({ eventUuid, itemUuid, name }: { eventUuid: string; itemUuid: string; name: string }) {
  return <ConfirmButton action={deleteEventItemAction} label="Remove" title="Remove item" message={`Remove ${name}? If it was already ordered it is only hidden.`} confirm="Remove" fields={{ event: eventUuid, item: itemUuid }} />;
}
