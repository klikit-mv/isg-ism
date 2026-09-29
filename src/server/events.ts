import { and, asc, count, desc, eq, gte, inArray, isNull, lt, ne, or, sum } from 'drizzle-orm';
import { db, schema } from '@/db';
import { fromLocalInput } from '@/lib/dates';
import { acceptsRegistrations, audienceLabel, isOpenForSection, parseSizes, sizeList } from '@/lib/events';
import * as money from '@/lib/money';
import { recordAudit } from './audit';
import { NotAccessibleError, ScoutError } from './errors';
import { recalculate } from './balances';
import { hasLivePayments } from './payments';
import { canAccessStudent, leaderStudentIds } from './scope';
import { hasRole, isActive, isAdmin, isLeader, type AuthUser } from './users';

export type EventRow = typeof schema.events.$inferSelect;
export type EventItemRow = typeof schema.eventItems.$inferSelect;
export type RegistrationRow = typeof schema.eventRegistrations.$inferSelect;

const E = schema.events;
const R = schema.eventRegistrations;
const RI = schema.eventRegistrationItems;

/** Start of today in the organisation timezone, as an instant. */
export function startOfToday(now = new Date()): Date {
  const local = new Intl.DateTimeFormat('en-CA', { timeZone: process.env.SCOUT_TIMEZONE ?? 'Indian/Maldives' }).format(now);
  return fromLocalInput(`${local}T00:00`) ?? now;
}

export const findEventByUuid = async (uuid: string): Promise<EventRow | null> =>
  (await db.select().from(E).where(and(eq(E.uuid, uuid), isNull(E.deletedAt))).limit(1))[0] ?? null;

export const findRegistrationByUuid = async (uuid: string): Promise<RegistrationRow | null> =>
  (await db.select().from(R).where(eq(R.uuid, uuid)).limit(1))[0] ?? null;

/** Admins manage every event; leaders manage the events they created. */
export const canManageEvent = (user: AuthUser, event: Pick<EventRow, 'createdBy'>): boolean =>
  isAdmin(user) || (isActive(user) && isLeader(user) && event.createdBy === user.id);

export interface EventInput {
  name: string;
  description?: string | null;
  location?: string | null;
  starts_at: string;
  ends_at?: string | null;
  registration_closes_at?: string | null;
  fee: string | number;
  capacity?: number | null;
  sections?: string[];
}

const attributes = (d: EventInput) => ({
  name: d.name,
  description: d.description ?? null,
  location: d.location ?? null,
  startsAt: fromLocalInput(d.starts_at) ?? new Date(d.starts_at),
  endsAt: fromLocalInput(d.ends_at),
  registrationClosesAt: fromLocalInput(d.registration_closes_at),
  fee: money.normalize(d.fee),
  capacity: d.capacity ?? null,
  sections: d.sections?.length ? [...new Set(d.sections)] : null,
});

export async function createEvent(data: EventInput, actor: AuthUser): Promise<EventRow> {
  const now = new Date();
  const [ins] = await db.insert(E).values({ ...attributes(data), status: 'draft', createdBy: actor.id, createdAt: now, updatedAt: now }).$returningId();
  const [event] = await db.select().from(E).where(eq(E.id, ins.id));
  await recordAudit('event.created', { type: 'event', id: event.uuid }, { name: event.name }, actor.id);
  return event;
}

export async function updateEvent(event: EventRow, data: EventInput, actor: AuthUser): Promise<void> {
  await db.update(E).set({ ...attributes(data), updatedAt: new Date() }).where(eq(E.id, event.id));
  await recordAudit('event.updated', { type: 'event', id: event.uuid }, { name: data.name }, actor.id);
}

export async function setEventStatus(event: EventRow, status: string, actor: AuthUser): Promise<void> {
  await db.update(E).set({ status, updatedAt: new Date() }).where(eq(E.id, event.id));
  await recordAudit('event.status_changed', { type: 'event', id: event.uuid }, { status }, actor.id);
}

export interface EventItemInput {
  name: string;
  description?: string | null;
  price: string | number;
  sizes?: string | null;
  size_guide?: string | null;
  stock?: number | null;
  max_per_registration?: number | null;
  active?: boolean;
}

export async function saveEventItem(event: EventRow, data: EventItemInput, actor: AuthUser, item?: EventItemRow | null): Promise<EventItemRow> {
  const { sizes, chart } = parseSizes(data.sizes);
  const attrs = {
    name: data.name,
    description: data.description ?? null,
    price: money.normalize(data.price),
    sizes,
    sizeChart: chart,
    sizeGuide: data.size_guide?.trim() || null,
    stock: data.stock ?? null,
    maxPerRegistration: Math.max(1, data.max_per_registration ?? 5),
    active: data.active ?? true,
    updatedAt: new Date(),
  };
  let saved: EventItemRow;
  if (item) {
    await db.update(schema.eventItems).set(attrs).where(eq(schema.eventItems.id, item.id));
    saved = { ...item, ...attrs };
  } else {
    const now = new Date();
    const [ins] = await db.insert(schema.eventItems).values({ ...attrs, eventId: event.id, createdAt: now }).$returningId();
    [saved] = await db.select().from(schema.eventItems).where(eq(schema.eventItems.id, ins.id));
  }
  await recordAudit('event.item_saved', { type: 'event', id: event.uuid }, { item: saved.name, price: saved.price }, actor.id);
  return saved;
}

export async function deleteEventItem(event: EventRow, item: EventItemRow, actor: AuthUser): Promise<void> {
  const [{ n }] = await db.select({ n: count() }).from(RI).where(eq(RI.eventItemId, item.id));
  if (Number(n) > 0) {
    await db.update(schema.eventItems).set({ active: false, updatedAt: new Date() }).where(eq(schema.eventItems.id, item.id));
    await recordAudit('event.item_deactivated', { type: 'event', id: event.uuid }, { item: item.name }, actor.id);
    return;
  }
  await recordAudit('event.item_deleted', { type: 'event', id: event.uuid }, { item: item.name }, actor.id);
  await db.delete(schema.eventItems).where(eq(schema.eventItems.id, item.id));
}

export const registeredCount = async (eventId: number): Promise<number> =>
  Number((await db.select({ n: count() }).from(R).where(and(eq(R.eventId, eventId), eq(R.status, 'registered'))))[0].n);

export interface OrderLineInput { item: string; quantity: number | string; size?: string | null }

export const MYSELF = 'me';

/**
 * Register a scout, or a leader themselves, with optional pre-ordered items.
 * Rovers and leaders may join any event; other scouts only their sections.
 */
export async function registerForEvent(
  event: EventRow,
  participant: { kind: 'student'; student: typeof schema.students.$inferSelect } | { kind: 'leader'; user: AuthUser },
  lines: OrderLineInput[],
  paymentOption: string,
  actor: AuthUser,
  notes?: string | null,
): Promise<RegistrationRow> {
  if (participant.kind === 'student') {
    if (!(await canAccessStudent(actor, participant.student))) throw new NotAccessibleError('You cannot register this scout.');
  } else if (!(hasRole(participant.user, 'leader') && (participant.user.id === actor.id || isAdmin(actor)))) {
    throw new ScoutError('Only leaders can register themselves.');
  }
  const name = participant.kind === 'student' ? participant.student.name : participant.user.name;
  const nationalId = participant.kind === 'student' ? participant.student.nationalId : participant.user.nationalId;
  const column = participant.kind === 'student' ? R.studentId : R.userId;
  const participantId = participant.kind === 'student' ? participant.student.id : participant.user.id;

  return db.transaction(async (tx) => {
    const [locked] = await tx.select().from(E).where(and(eq(E.id, event.id), isNull(E.deletedAt))).for('update');
    if (!locked || !acceptsRegistrations(locked)) throw new ScoutError('Registration for this event is not open.');
    if (participant.kind === 'student' && (!participant.student.section || !isOpenForSection(locked.sections, participant.student.section))) {
      throw new ScoutError(`${locked.name} is only for ${audienceLabel(locked.sections)}.`);
    }
    const [existing] = await tx.select().from(R).where(and(eq(R.eventId, locked.id), eq(column, participantId))).for('update');
    if (existing?.status === 'registered') throw new ScoutError(`${name} is already registered for ${locked.name}.`);
    if (locked.capacity !== null) {
      const [{ n }] = await tx.select({ n: count() }).from(R).where(and(eq(R.eventId, locked.id), eq(R.status, 'registered')));
      if (Number(n) >= locked.capacity) throw new ScoutError('This event is full.');
    }

    const orderLines = await buildOrderLines(tx, locked, lines, existing?.id ?? null);
    const itemsTotal = money.add('0', ...orderLines.map((l) => l.totalAmount));
    const total = money.add(locked.fee, itemsTotal);
    const now = new Date();
    const attrs = {
      registeredBy: actor.id, status: 'registered', paymentOption, feeAmount: money.normalize(locked.fee), itemsAmount: itemsTotal,
      totalAmount: total, paidAmount: '0.00', outstandingAmount: total, paymentStatus: 'Pending', notes: notes || null, updatedAt: now,
    };
    let registrationId: number;
    if (existing) {
      await tx.delete(RI).where(eq(RI.eventRegistrationId, existing.id));
      await tx.update(R).set(attrs).where(eq(R.id, existing.id));
      registrationId = existing.id;
    } else {
      const [ins] = await tx.insert(R).values({
        ...attrs, eventId: locked.id, createdAt: now,
        ...(participant.kind === 'student' ? { studentId: participantId } : { userId: participantId }),
      }).$returningId();
      registrationId = ins.id;
    }
    for (const l of orderLines) await tx.insert(RI).values({ ...l, eventRegistrationId: registrationId, createdAt: now, updatedAt: now });
    await recalculate('event_registration', registrationId, tx);
    await recordAudit('event.registered', { type: 'event_registration', id: registrationId }, { event: locked.name, participant: nationalId, total }, actor.id, tx);
    return (await tx.select().from(R).where(eq(R.id, registrationId)))[0];
  });
}

/** Quantity already ordered on active registrations. */
async function orderedQuantity(conn: typeof db, itemId: number, exceptRegistrationId: number | null): Promise<number> {
  const [row] = await conn.select({ q: sum(RI.quantity) }).from(RI).innerJoin(R, eq(R.id, RI.eventRegistrationId))
    .where(and(eq(RI.eventItemId, itemId), eq(R.status, 'registered'), exceptRegistrationId ? ne(R.id, exceptRegistrationId) : undefined));
  return Number(row?.q ?? 0);
}

export async function itemRemaining(item: Pick<EventItemRow, 'id' | 'stock'>): Promise<number | null> {
  return item.stock === null ? null : Math.max(0, item.stock - (await orderedQuantity(db, item.id, null)));
}

async function buildOrderLines(tx: typeof db, event: EventRow, lines: OrderLineInput[], exceptRegistrationId: number | null) {
  const out: { eventItemId: number; itemName: string; size: string | null; quantity: number; unitPrice: string; totalAmount: string }[] = [];
  const wanted = new Map<number, number>();
  for (const line of lines) {
    const quantity = Math.trunc(Number(line.quantity) || 0);
    if (quantity <= 0) continue;
    const [item] = await tx.select().from(schema.eventItems).where(and(eq(schema.eventItems.eventId, event.id), eq(schema.eventItems.uuid, line.item))).for('update');
    if (!item || !item.active) throw new ScoutError('One of the chosen items is no longer available.');
    if (quantity > item.maxPerRegistration) throw new ScoutError(`You can order at most ${item.maxPerRegistration} × ${item.name}.`);
    const sizes = sizeList(item);
    const size = (line.size ?? '').trim();
    if (sizes.length && !sizes.includes(size)) throw new ScoutError(`Choose a size for ${item.name}.`);
    const total = (wanted.get(item.id) ?? 0) + quantity;
    wanted.set(item.id, total);
    if (item.stock !== null) {
      const ordered = await orderedQuantity(tx, item.id, exceptRegistrationId);
      if (ordered + total > item.stock) throw new ScoutError(`Only ${Math.max(0, item.stock - ordered)} × ${item.name} left.`);
    }
    out.push({ eventItemId: item.id, itemName: item.name, size: sizes.length ? size : null, quantity, unitPrice: money.normalize(item.price), totalAmount: money.mul(item.price, quantity) });
  }
  return out;
}

/** The buyer side or event staff may cancel until money is received. */
export async function cancelRegistration(registration: RegistrationRow, actor: AuthUser): Promise<void> {
  await db.transaction(async (tx) => {
    const [locked] = await tx.select().from(R).where(eq(R.id, registration.id)).for('update');
    if (!locked || locked.status !== 'registered') return;
    if (money.isPositive(locked.paidAmount) || (await hasLivePayments('event_registration', locked.id, tx))) {
      throw new ScoutError('This registration has a payment and cannot be cancelled. Ask an administrator.');
    }
    await tx.update(R).set({ status: 'cancelled', updatedAt: new Date() }).where(eq(R.id, locked.id));
    await recordAudit('event.registration_cancelled', { type: 'event_registration', id: locked.id }, {}, actor.id, tx);
  });
}

/** What to order: quantity per item and size across active registrations. */
export async function orderSummary(eventId: number) {
  const rows = await db.select({ item: RI.itemName, size: RI.size, quantity: sum(RI.quantity), amount: sum(RI.totalAmount) })
    .from(RI).innerJoin(R, eq(R.id, RI.eventRegistrationId))
    .where(and(eq(R.eventId, eventId), eq(R.status, 'registered')))
    .groupBy(RI.itemName, RI.size).orderBy(asc(RI.itemName), asc(RI.size));
  return rows.map((r) => ({ item: r.item, size: r.size, quantity: Number(r.quantity), amount: money.normalize(r.amount) }));
}

/** Events a signed-in person may see. Drafts only for admins and the leader who made them. */
export async function listEvents(user: AuthUser, past: boolean) {
  const today = startOfToday();
  const visible = isAdmin(user) ? undefined : or(ne(E.status, 'draft'), isLeader(user) ? eq(E.createdBy, user.id) : undefined);
  const rows = await db.select().from(E).where(and(isNull(E.deletedAt), visible, past ? lt(E.startsAt, today) : gte(E.startsAt, today)))
    .orderBy(past ? desc(E.startsAt) : asc(E.startsAt)).limit(100);
  const counts = rows.length
    ? await db.select({ id: R.eventId, n: count() }).from(R).where(and(inArray(R.eventId, rows.map((e) => e.id)), eq(R.status, 'registered'))).groupBy(R.eventId)
    : [];
  return rows.map((e) => ({ event: e, registered: Number(counts.find((c) => c.id === e.id)?.n ?? 0) }));
}

/** Open or closed events that have not started, for the public pages. */
export async function publicEvents(limit = 24) {
  const rows = await db.select().from(E).where(and(isNull(E.deletedAt), inArray(E.status, ['open', 'closed']), gte(E.startsAt, startOfToday()))).orderBy(asc(E.startsAt)).limit(limit);
  const counts = rows.length
    ? await db.select({ id: R.eventId, n: count() }).from(R).where(and(inArray(R.eventId, rows.map((e) => e.id)), eq(R.status, 'registered'))).groupBy(R.eventId)
    : [];
  return rows.map((e) => ({ event: e, registered: Number(counts.find((c) => c.id === e.id)?.n ?? 0) }));
}

export async function findPublicEvent(uuid: string): Promise<EventRow | null> {
  const event = await findEventByUuid(uuid);
  return event && ['open', 'closed'].includes(event.status) && event.startsAt >= startOfToday() ? event : null;
}

export const itemsOf = (eventId: number, onlyActive = false) =>
  db.select().from(schema.eventItems).where(and(eq(schema.eventItems.eventId, eventId), onlyActive ? eq(schema.eventItems.active, true) : undefined)).orderBy(asc(schema.eventItems.name));

const registrationSelect = {
  registration: R,
  studentName: schema.students.name,
  studentSection: schema.students.section,
  userName: schema.users.name,
  eventName: E.name,
  eventUuid: E.uuid,
} as const;

export type RegistrationView = Awaited<ReturnType<typeof registrationsOfEvent>>[number];

async function withItems<T extends { registration: RegistrationRow; studentName: string | null; userName: string | null }>(rows: T[]) {
  const ids = rows.map((r) => r.registration.id);
  const items = ids.length ? await db.select().from(RI).where(inArray(RI.eventRegistrationId, ids)) : [];
  return rows.map((r) => ({
    ...r,
    participant: r.studentName ?? r.userName ?? '',
    isLeader: r.registration.studentId === null,
    items: items.filter((i) => i.eventRegistrationId === r.registration.id),
  }));
}

/** Everyone registered (and cancelled) for one event, for its managers. */
export async function registrationsOfEvent(eventId: number) {
  const rows = await db.select(registrationSelect).from(R)
    .innerJoin(E, eq(E.id, R.eventId))
    .leftJoin(schema.students, eq(schema.students.id, R.studentId))
    .leftJoin(schema.users, eq(schema.users.id, R.userId))
    .where(eq(R.eventId, eventId)).orderBy(desc(R.status), desc(R.createdAt));
  return withItems(rows);
}

/** The registrations this person can act for: their scouts, themselves, or ones they made. */
export async function registrationsForUser(user: AuthUser, eventId?: number) {
  const ids = await leaderStudentIds(user);
  const mine = ids === null ? undefined : or(inArray(R.studentId, ids.length ? ids : [0]), eq(R.userId, user.id), eq(R.registeredBy, user.id));
  const rows = await db.select(registrationSelect).from(R)
    .innerJoin(E, eq(E.id, R.eventId))
    .leftJoin(schema.students, eq(schema.students.id, R.studentId))
    .leftJoin(schema.users, eq(schema.users.id, R.userId))
    .where(and(mine, eventId ? eq(R.eventId, eventId) : undefined)).orderBy(desc(R.createdAt)).limit(100);
  return withItems(rows);
}
