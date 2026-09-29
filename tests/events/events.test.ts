import { eq } from 'drizzle-orm';
import { describe, expect, it } from 'vitest';
import { db, schema } from '@/db';
import { acceptsRegistrations, audienceLabel, isOpenForSection, parseSizes, sizeLabel, sizesText } from '@/lib/events';
import { cancelRegistration, createEvent, findPublicEvent, orderSummary, publicEvents, registerForEvent, saveEventItem, setEventStatus } from '@/server/events';
import { loadPayableOrThrow } from '@/server/payables';
import { submitPayment } from '@/server/payments';
import { makeAdmin, makeLeader, makeParentOf, makeStudent } from '../factories';

const future = (days: number) => {
  const d = new Date(Date.now() + days * 86400_000);
  return d.toISOString().slice(0, 16);
};

async function openEvent(over: Partial<Parameters<typeof createEvent>[0]> = {}) {
  const admin = await makeAdmin();
  const event = await createEvent({ name: 'Camp', starts_at: future(10), fee: '100.00', ...over }, admin);
  await setEventStatus(event, 'open', admin);
  const [fresh] = await db.select().from(schema.events).where(eq(schema.events.id, event.id));
  return { event: fresh, admin };
}

describe('event rules', () => {
  it('rovers and leaders can join any event; scouts only their sections', () => {
    expect(isOpenForSection(['Cub'], 'Cub')).toBe(true);
    expect(isOpenForSection(['Cub'], 'Scout')).toBe(false);
    expect(isOpenForSection(['Cub'], 'Rover')).toBe(true);
    expect(isOpenForSection([], 'Scout')).toBe(true);
    expect(audienceLabel(['Cub'])).toBe('Cub, Leaders and Rovers');
  });

  it('registration is open only while open and before the deadline', () => {
    expect(acceptsRegistrations({ status: 'open', registrationClosesAt: null })).toBe(true);
    expect(acceptsRegistrations({ status: 'open', registrationClosesAt: new Date(Date.now() - 1000) })).toBe(false);
    expect(acceptsRegistrations({ status: 'closed', registrationClosesAt: null })).toBe(false);
  });

  it('parses sizes with measurements', () => {
    expect(parseSizes('S, M, L')).toEqual({ sizes: ['S', 'M', 'L'], chart: null });
    const parsed = parseSizes('S: Chest 34 in\nM: Chest 38 in, Length 28 in\nL');
    expect(parsed.sizes).toEqual(['S', 'M', 'L']);
    expect(parsed.chart).toEqual({ S: 'Chest 34 in', M: 'Chest 38 in, Length 28 in' });
    const item = { sizes: parsed.sizes, sizeChart: parsed.chart };
    expect(sizeLabel(item, 'M')).toBe('M (Chest 38 in, Length 28 in)');
    expect(sizesText(item)).toBe('S: Chest 34 in\nM: Chest 38 in, Length 28 in\nL');
  });
});

describe('event registration', () => {
  it('a parent registers a child with sized items and pays the total', async () => {
    const { event, admin } = await openEvent({ fee: '100.00' });
    const shirt = await saveEventItem(event, { name: 'T-shirt', price: '30.00', sizes: 'S: Chest 34 in\nM: Chest 38 in', max_per_registration: 3 }, admin);
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);

    const reg = await registerForEvent(event, { kind: 'student', student: scout }, [{ item: shirt.uuid, quantity: 2, size: 'M' }], 'online', parent);
    expect(reg.itemsAmount).toBe('60.00');
    expect(reg.totalAmount).toBe('160.00');
    expect(reg.outstandingAmount).toBe('160.00');
    expect(await orderSummary(event.id)).toEqual([{ item: 'T-shirt', size: 'M', quantity: 2, amount: '60.00' }]);

    await submitPayment(await loadPayableOrThrow('event_registration', { id: reg.id }), admin, '160.00', 'cash');
    expect((await loadPayableOrThrow('event_registration', { id: reg.id })).status).toBe('Paid');
    await expect(cancelRegistration(reg, parent)).rejects.toThrow('has a payment');
  });

  it('validates sizes, quantity limits and stock', async () => {
    const { event, admin } = await openEvent();
    const shirt = await saveEventItem(event, { name: 'T-shirt', price: '30', sizes: 'S, M', stock: 3, max_per_registration: 2 }, admin);
    const a = await makeStudent();
    const b = await makeStudent();
    const pa = await makeParentOf(a);
    const pb = await makeParentOf(b);
    await expect(registerForEvent(event, { kind: 'student', student: a }, [{ item: shirt.uuid, quantity: 1 }], 'cash', pa)).rejects.toThrow('Choose a size');
    await expect(registerForEvent(event, { kind: 'student', student: a }, [{ item: shirt.uuid, quantity: 3, size: 'S' }], 'cash', pa)).rejects.toThrow('at most 2');
    await registerForEvent(event, { kind: 'student', student: a }, [{ item: shirt.uuid, quantity: 2, size: 'S' }], 'cash', pa);
    await expect(registerForEvent(event, { kind: 'student', student: b }, [{ item: shirt.uuid, quantity: 2, size: 'S' }], 'cash', pb)).rejects.toThrow('Only 1 × T-shirt left');
  });

  it('enforces section, duplicates, capacity and access', async () => {
    const { event } = await openEvent({ sections: ['Cub'], capacity: 1 });
    const cub = await makeStudent({ section: 'Cub' });
    const scout = await makeStudent({ section: 'Scout' });
    const rover = await makeStudent({ section: 'Rover' });
    const pCub = await makeParentOf(cub);
    const pScout = await makeParentOf(scout);
    const pRover = await makeParentOf(rover);

    await expect(registerForEvent(event, { kind: 'student', student: scout }, [], 'cash', pScout)).rejects.toThrow('is only for');
    await expect(registerForEvent(event, { kind: 'student', student: cub }, [], 'cash', pScout)).rejects.toThrow('cannot register this scout');
    await registerForEvent(event, { kind: 'student', student: cub }, [], 'cash', pCub);
    await expect(registerForEvent(event, { kind: 'student', student: cub }, [], 'cash', pCub)).rejects.toThrow('already registered');
    await expect(registerForEvent(event, { kind: 'student', student: rover }, [], 'cash', pRover)).rejects.toThrow('This event is full');
  });

  it('leaders register themselves; others cannot; free events count as paid', async () => {
    const { event } = await openEvent({ fee: '0.00' });
    const leader = await makeLeader();
    const reg = await registerForEvent(event, { kind: 'leader', user: leader }, [], 'cash', leader);
    expect(reg.userId).toBe(leader.id);
    expect(reg.studentId).toBeNull();
    expect(reg.paymentStatus).toBe('Paid');
    const parent = await makeParentOf(await makeStudent());
    await expect(registerForEvent(event, { kind: 'leader', user: parent }, [], 'cash', parent)).rejects.toThrow('Only leaders');
  });

  it('cancelling frees the place and the scout can register again', async () => {
    const { event } = await openEvent({ capacity: 1 });
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    const reg = await registerForEvent(event, { kind: 'student', student: scout }, [], 'cash', parent);
    await cancelRegistration(reg, parent);
    const again = await registerForEvent(event, { kind: 'student', student: scout }, [], 'online', parent);
    expect(again.id).toBe(reg.id);
    expect(again.status).toBe('registered');
  });

  it('closed or draft events refuse registration', async () => {
    const { event, admin } = await openEvent();
    const scout = await makeStudent();
    const parent = await makeParentOf(scout);
    await setEventStatus(event, 'closed', admin);
    await expect(registerForEvent(event, { kind: 'student', student: scout }, [], 'cash', parent)).rejects.toThrow('not open');
  });
});

describe('public events', () => {
  it('lists open and closed upcoming events only', async () => {
    const admin = await makeAdmin();
    const draft = await createEvent({ name: 'Draft', starts_at: future(5), fee: '0' }, admin);
    const { event: open } = await openEvent();
    const past = await createEvent({ name: 'Past', starts_at: future(-5), fee: '0' }, admin);
    await setEventStatus(past, 'open', admin);
    const names = (await publicEvents()).map((e) => e.event.name);
    expect(names).toEqual(['Camp']);
    expect(await findPublicEvent(open.uuid)).not.toBeNull();
    expect(await findPublicEvent(draft.uuid)).toBeNull();
    expect(await findPublicEvent(past.uuid)).toBeNull();
  });
});
