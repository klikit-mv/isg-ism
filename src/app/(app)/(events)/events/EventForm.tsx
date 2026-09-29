'use client';

import { createEventAction, updateEventAction } from '@/app/actions/events';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { CheckboxGroup, Input, Textarea } from '@/components/form/fields';
import { ScoutSection } from '@/lib/enums';

export interface EventFormValues {
  uuid?: string;
  name?: string;
  description?: string | null;
  location?: string | null;
  starts_at: string;
  ends_at?: string;
  registration_closes_at?: string;
  fee: string;
  capacity?: number | null;
  sections?: string[];
}

export function EventForm({ event }: { event: EventFormValues }) {
  return (
    <ActionForm action={event.uuid ? updateEventAction : createEventAction}>
      {event.uuid && <input type="hidden" name="uuid" value={event.uuid} />}
      <Input name="name" label="Event name" defaultValue={event.name} required />
      <Textarea name="description" label="Details" rows={5} defaultValue={event.description} />
      <Input name="location" label="Location" defaultValue={event.location} />
      <div className="grid gap-3 sm:grid-cols-3">
        <Input name="starts_at" label="Starts" type="datetime-local" defaultValue={event.starts_at} required />
        <Input name="ends_at" label="Ends" type="datetime-local" defaultValue={event.ends_at} />
        <Input name="registration_closes_at" label="Registration closes" type="datetime-local" defaultValue={event.registration_closes_at} />
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <Input name="fee" label="Registration fee (MVR)" type="number" step="0.01" min="0" defaultValue={event.fee} required />
        <Input name="capacity" label="Capacity (optional)" type="number" min="1" defaultValue={event.capacity} />
      </div>
      <CheckboxGroup name="sections" legend="Open to sections" options={ScoutSection.options()} defaultValues={event.sections ?? []} help="Leave empty for every section. Rovers and leaders can always register." />
      <div className="flex justify-end"><SubmitButton>{event.uuid ? 'Save event' : 'Create event'}</SubmitButton></div>
    </ActionForm>
  );
}
