'use server';

import { eq } from 'drizzle-orm';
import { forbidden, notFound, redirect } from 'next/navigation';
import { revalidatePath } from 'next/cache';
import { db, schema } from '@/db';
import { echo, handle, simple, type ActionState } from '@/server/action';
import {
  MYSELF, canManageEvent, cancelRegistration, createEvent, deleteEventItem, findEventByUuid, findRegistrationByUuid, registerForEvent, saveEventItem, setEventStatus, updateEvent,
  type EventItemRow,
} from '@/server/events';
import { ScoutError } from '@/server/errors';
import { flash } from '@/server/flash';
import { canAccessStudentId } from '@/server/scope';
import { requireUser } from '@/server/session';
import { isActive, isStaff } from '@/server/users';
import { EventStatus, PaymentMethod, ScoutSection } from '@/lib/enums';
import { formatMoney } from '@/lib/money';
import { inEnum, parseForm, validate } from '@/lib/validate';

const eventRules = {
  name: ['required', 'string', 'max:255'],
  description: ['nullable', 'string', 'max:5000'],
  location: ['nullable', 'string', 'max:255'],
  starts_at: ['required', 'datetime'],
  ends_at: ['nullable', 'datetime', 'after_or_equal:starts_at'],
  registration_closes_at: ['nullable', 'datetime', 'before_or_equal:starts_at'],
  fee: ['required', 'numeric', 'min:0', 'max:9999999'],
  capacity: ['nullable', 'integer', 'min:1', 'max:100000'],
  sections: ['array'],
  'sections.*': [inEnum(ScoutSection)],
};

async function staffUser() {
  const user = await requireUser();
  if (!isActive(user) || !isStaff(user)) forbidden();
  return user;
}

async function managedEvent(uuid: unknown) {
  const user = await requireUser();
  const event = await findEventByUuid(String(uuid ?? ''));
  if (!event) notFound();
  if (!canManageEvent(user, event)) forbidden();
  return { user, event };
}

export async function createEventAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await staffUser();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(input, eventRules as never);
    const event = await createEvent(data as never, user);
    await flash('success', 'The event was created as a draft. Add any pre-order items, then open registration.');
    redirect(`/events/${event.uuid}`);
  }, echo(input));
}

export async function updateEventAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const input = parseForm(formData);
  const { user, event } = await managedEvent(input.uuid);
  return handle(async () => {
    const data = await validate(input, eventRules as never);
    await updateEvent(event, data as never, user);
    await flash('success', 'The event was saved.');
    redirect(`/events/${event.uuid}`);
  }, echo(input));
}

export async function eventStatusAction(formData: FormData): Promise<void> {
  const { user, event } = await managedEvent(formData.get('uuid'));
  await simple(async () => {
    const data = await validate({ status: formData.get('status') }, { status: ['required', inEnum(EventStatus)] });
    await setEventStatus(event, data.status, user);
    await flash('success', `The event is now: ${EventStatus.label(data.status)}.`);
    revalidatePath(`/events/${event.uuid}`);
  });
}

const itemRules = {
  name: ['required', 'string', 'max:255'],
  description: ['nullable', 'string', 'max:1000'],
  price: ['required', 'numeric', 'min:0', 'max:9999999'],
  sizes: ['nullable', 'string', 'max:2000'],
  size_guide: ['nullable', 'string', 'max:1000'],
  stock: ['nullable', 'integer', 'min:0', 'max:100000'],
  max_per_registration: ['required', 'integer', 'min:1', 'max:100'],
  active: ['sometimes', 'boolean'],
};

async function itemOf(eventId: number, uuid: unknown): Promise<EventItemRow | null> {
  if (!uuid) return null;
  const [item] = await db.select().from(schema.eventItems).where(eq(schema.eventItems.uuid, String(uuid))).limit(1);
  if (!item || item.eventId !== eventId) notFound();
  return item;
}

export async function saveEventItemAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const input = parseForm(formData);
  const { user, event } = await managedEvent(input.event);
  const item = await itemOf(event.id, input.item);
  return handle(async () => {
    const data = await validate(input, itemRules as never);
    const payload = { ...(data as Record<string, unknown>), active: input.active === undefined ? !item : !!data.active };
    const saved = await saveEventItem(event, payload as never, user, item);
    await flash('success', `${saved.name} was ${item ? 'saved' : 'added'}.`);
    revalidatePath(`/events/${event.uuid}`);
  }, echo(input));
}

export async function deleteEventItemAction(formData: FormData): Promise<void> {
  const { user, event } = await managedEvent(formData.get('event'));
  const item = await itemOf(event.id, formData.get('item'));
  if (!item) notFound();
  await simple(async () => {
    await deleteEventItem(event, item, user);
    await flash('success', 'The item was removed. Items already ordered are kept on those registrations.');
    revalidatePath(`/events/${event.uuid}`);
  });
}

export async function registerAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await requireUser();
  const input = parseForm(formData);
  const event = await findEventByUuid(String(input.event ?? ''));
  if (!event) notFound();
  return handle(async () => {
    const data = await validate(input, {
      student: ['required', 'string', 'max:64'],
      payment_option: ['required', inEnum(PaymentMethod)],
      items: ['array'],
      'items.*.item': ['required', 'uuid'],
      'items.*.quantity': ['nullable', 'integer', 'min:0', 'max:100'],
      'items.*.size': ['nullable', 'string', 'max:50'],
      notes: ['nullable', 'string', 'max:500'],
    });
    const lines = Object.values((data.items ?? {}) as Record<string, { item: string; quantity?: number; size?: string }>).map((l) => ({ item: l.item, quantity: l.quantity ?? 0, size: l.size }));
    let participant: Parameters<typeof registerForEvent>[1];
    let who = 'You are';
    if (data.student === MYSELF) {
      participant = { kind: 'leader', user };
    } else {
      const [student] = await db.select().from(schema.students).where(eq(schema.students.uuid, data.student)).limit(1);
      if (!student) throw new ScoutError('Choose who is taking part.', 'student');
      participant = { kind: 'student', student };
      who = `${student.name} is`;
    }
    const registration = await registerForEvent(event, participant, lines, data.payment_option, user, data.notes);
    const message = `${who} registered for ${event.name}. Total: ${formatMoney(registration.totalAmount)}.`;
    if (registration.paymentStatus === 'Paid') {
      await flash('success', `${message} Nothing to pay.`);
      redirect(`/events/${event.uuid}`);
    }
    if (registration.paymentOption === 'online') {
      await flash('success', `${message} Upload your payment proof now.`);
      redirect(`/events/${event.uuid}?open_payment=${registration.uuid}`);
    }
    await flash('success', `${message} Please pay in cash to a leader; they will record it.`);
    redirect(`/events/${event.uuid}`);
  }, echo(input));
}

export async function cancelRegistrationAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  const registration = await findRegistrationByUuid(String(formData.get('uuid') ?? ''));
  if (!registration) notFound();
  const [event] = await db.select().from(schema.events).where(eq(schema.events.id, registration.eventId));
  const allowed = canManageEvent(user, event)
    || (registration.userId !== null && registration.userId === user.id)
    || (registration.studentId !== null && (await canAccessStudentId(user, registration.studentId)));
  if (!allowed) forbidden();
  await simple(async () => {
    await cancelRegistration(registration, user);
    await flash('success', 'The registration was cancelled.');
    revalidatePath('/events', 'layout');
  });
}
