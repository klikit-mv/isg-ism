import Link from 'next/link';
import { notFound } from 'next/navigation';
import { Badge } from '@/components/Badge';
import { ActionButton } from '@/components/ConfirmButton';
import { Card, Empty } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { OpenPaymentOnLoad } from '@/components/finance/PayModal';
import { PayButton } from '@/components/finance/PayButton';
import { PaymentModal } from '@/components/finance/PaymentModal';
import { cancelRegistrationAction, eventStatusAction } from '@/app/actions/events';
import { formatDateTime } from '@/lib/dates';
import { EventRegistrationStatus, EventStatus, FeeStatus } from '@/lib/enums';
import { acceptsRegistrations, audienceLabel, isOpenForSection, sizeLabel, sizeList, sizesText } from '@/lib/events';
import { formatMoney, isPositive } from '@/lib/money';
import { MYSELF, canManageEvent, findEventByUuid, itemRemaining, itemsOf, orderSummary, registeredCount, registrationsOfEvent, registrationsForUser } from '@/server/events';
import { studentScope, canAccessStudent } from '@/server/scope';
import { requireUser } from '@/server/session';
import { isActive, isLeader } from '@/server/users';
import { and, asc, eq, isNull } from 'drizzle-orm';
import { db, schema } from '@/db';
import { DeleteEventItemButton, EventItemModal } from './ItemModal';
import { RegisterForm } from './RegisterForm';

export default async function EventPage({ params, searchParams }: { params: Promise<{ uuid: string }>; searchParams: Promise<{ open_payment?: string }> }) {
  const user = await requireUser();
  const event = await findEventByUuid((await params).uuid);
  if (!event) notFound();
  const manage = canManageEvent(user, event);
  if (event.status === 'draft' && !manage) notFound();
  const { open_payment } = await searchParams;

  const S = schema.students;
  const accessible = isActive(user)
    ? (await db.select().from(S).where(and(isNull(S.deletedAt), eq(S.status, 'active'), await studentScope(user))).orderBy(asc(S.name)))
    : [];
  const mine: typeof accessible = [];
  for (const s of accessible) if (await canAccessStudent(user, s)) mine.push(s);

  const [items, count, myRegs, registrations, summary] = await Promise.all([
    itemsOf(event.id, !manage),
    registeredCount(event.id),
    registrationsForUser(user, event.id),
    manage ? registrationsOfEvent(event.id) : [],
    manage ? orderSummary(event.id) : [],
  ]);
  const registeredIds = new Set(myRegs.filter((r) => r.registration.status === 'registered').map((r) => r.registration.studentId));
  const eligible = mine.filter((s) => s.section && isOpenForSection(event.sections, s.section) && !registeredIds.has(s.id));
  const canRegisterSelf = isActive(user) && isLeader(user) && !myRegs.some((r) => r.registration.userId === user.id && r.registration.status === 'registered');
  const participants = [
    ...(canRegisterSelf ? [{ value: MYSELF, label: `Myself (${user.name}, leader)` }] : []),
    ...eligible.map((s) => ({ value: s.uuid, label: `${s.name}${s.section ? ` — ${s.section}` : ''}` })),
  ];
  const open = acceptsRegistrations(event);
  const registerItems = await Promise.all(items.filter((i) => i.active).map(async (i) => ({
    uuid: i.uuid, name: i.name, description: i.description, price: i.price,
    sizes: sizeList(i).map((s) => ({ value: s, label: sizeLabel(i, s) })), sizeGuide: i.sizeGuide, max: i.maxPerRegistration, remaining: await itemRemaining(i),
  })));
  const payTarget = open_payment ? myRegs.find((r) => r.registration.uuid === open_payment) : undefined;

  return (
    <>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">{event.name} <Badge of={EventStatus} value={event.status} /></h1>
          <p className="mt-1 text-sm text-gray-500">{formatDateTime(event.startsAt)}{event.endsAt ? ` – ${formatDateTime(event.endsAt)}` : ''}{event.location ? ` · ${event.location}` : ''}</p>
        </div>
        {manage && (
          <div className="flex flex-wrap items-center gap-2">
            <Link href={`/events/${event.uuid}/edit`} className="btn-secondary btn-sm">Edit</Link>
            {event.status !== 'open' && event.status !== 'cancelled' && <ActionButton action={eventStatusAction} label="Open registration" variant="accent" fields={{ uuid: event.uuid, status: 'open' }} />}
            {event.status === 'open' && <ActionButton action={eventStatusAction} label="Close registration" fields={{ uuid: event.uuid, status: 'closed' }} />}
            {event.status !== 'cancelled' && <ActionButton action={eventStatusAction} label="Cancel event" variant="danger" fields={{ uuid: event.uuid, status: 'cancelled' }} />}
            {event.status !== 'draft' && <ActionButton action={eventStatusAction} label="Back to draft" fields={{ uuid: event.uuid, status: 'draft' }} />}
          </div>
        )}
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="space-y-3 p-5 lg:col-span-2">
          {event.description && <p className="whitespace-pre-line text-sm">{event.description}</p>}
          <dl className="grid grid-cols-2 gap-3 text-sm">
            <div><dt className="text-gray-500">Fee</dt><dd className="font-medium">{isPositive(event.fee) ? formatMoney(event.fee) : 'Free'}</dd></div>
            <div><dt className="text-gray-500">Open to</dt><dd className="font-medium">{audienceLabel(event.sections)}</dd></div>
            <div><dt className="text-gray-500">Registered</dt><dd className="font-medium">{count}{event.capacity ? ` of ${event.capacity}` : ''}</dd></div>
            <div><dt className="text-gray-500">Registration closes</dt><dd className="font-medium">{event.registrationClosesAt ? formatDateTime(event.registrationClosesAt) : 'When the event starts'}</dd></div>
          </dl>
          {items.filter((i) => i.active).length > 0 && (
            <div>
              <h3 className="mb-1 text-sm font-semibold">Pre-order items</h3>
              <ul className="space-y-2 text-sm">
                {items.filter((i) => i.active).map((i) => (
                  <li key={i.id}>
                    <span className="font-medium">{i.name}</span> — {formatMoney(i.price)}{i.description ? ` · ${i.description}` : ''}
                    {sizeList(i).length > 0 && (
                      <ul className="ml-4 list-disc text-xs text-gray-600 dark:text-gray-300">
                        {sizeList(i).map((s) => <li key={s}>{sizeLabel(i, s)}</li>)}
                      </ul>
                    )}
                    {i.sizeGuide && <p className="text-xs text-gray-500">{i.sizeGuide}</p>}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </Card>

        <Card className="p-5">
          <h2 className="mb-3 font-semibold">Register</h2>
          {!open ? <p className="text-sm text-gray-500">Registration is not open{event.status === 'open' ? ' any more' : ''}.</p>
            : participants.length === 0 ? <p className="text-sm text-gray-500">There is nobody you can register for this event, or they are already registered.</p>
            : <RegisterForm eventUuid={event.uuid} fee={event.fee} participants={participants} items={registerItems} />}
        </Card>
      </div>

      <h2 className="mb-2 mt-8 text-lg font-semibold">Your registrations</h2>
      {myRegs.length === 0 ? <Empty message="You have not registered anyone for this event." /> : (
        <Table headers={['Participant', 'Items', 'Total', 'Outstanding', 'Payment', 'Status', '']}>
          {myRegs.map(({ registration: r, participant, isLeader: leader, items: lines }) => (
            <tr key={r.id}>
              <td data-label="Participant" className="font-medium">{participant}{leader && <span className="text-xs text-gray-400"> (leader)</span>}</td>
              <td data-label="Items">{lines.map((l) => `${l.itemName}${l.size ? ` ${l.size}` : ''} × ${l.quantity}`).join(', ') || '—'}</td>
              <td data-label="Total">{formatMoney(r.totalAmount)}</td>
              <td data-label="Outstanding">{formatMoney(r.outstandingAmount)}</td>
              <td data-label="Payment"><Badge of={FeeStatus} value={r.paymentStatus} /></td>
              <td data-label="Status"><Badge of={EventRegistrationStatus} value={r.status} /></td>
              <td className="text-right">
                {r.status === 'registered' && (
                  <div className="flex justify-end gap-2">
                    <PayButton payable={{ type: 'event_registration', id: r.id, uuid: r.uuid, studentId: r.studentId, userId: r.userId, amountDue: r.totalAmount, paidAmount: r.paidAmount, outstandingAmount: r.outstandingAmount, status: r.paymentStatus, closed: false, description: `Event — ${event.name} (${participant})` }} />
                    {r.paymentStatus === 'Pending' && parseFloat(r.paidAmount) === 0 && <ActionButton action={cancelRegistrationAction} label="Cancel" variant="danger" fields={{ uuid: r.uuid }} />}
                  </div>
                )}
              </td>
            </tr>
          ))}
        </Table>
      )}

      {manage && (
        <>
          <div className="mb-2 mt-8 flex items-center justify-between">
            <h2 className="text-lg font-semibold">Pre-order items</h2>
            <EventItemModal eventUuid={event.uuid} />
          </div>
          {items.length === 0 ? <Empty message="No items yet. Add T-shirts, badges or anything members can pre-order." /> : (
            <Table headers={['Item', 'Price', 'Sizes', 'Stock', 'Max', 'Available', '']}>
              {items.map((i) => (
                <tr key={i.id}>
                  <td data-label="Item" className="font-medium">{i.name}</td>
                  <td data-label="Price">{formatMoney(i.price)}</td>
                  <td data-label="Sizes" className="whitespace-pre-line text-xs">{sizesText(i) || '—'}</td>
                  <td data-label="Stock">{i.stock ?? '∞'}</td>
                  <td data-label="Max">{i.maxPerRegistration}</td>
                  <td data-label="Available">{i.active ? 'Yes' : 'No'}</td>
                  <td className="text-right">
                    <div className="flex justify-end gap-2">
                      <EventItemModal eventUuid={event.uuid} item={{ uuid: i.uuid, name: i.name, description: i.description, price: i.price, sizesText: sizesText(i), sizeGuide: i.sizeGuide, stock: i.stock, maxPerRegistration: i.maxPerRegistration, active: i.active }} />
                      <DeleteEventItemButton eventUuid={event.uuid} itemUuid={i.uuid} name={i.name} />
                    </div>
                  </td>
                </tr>
              ))}
            </Table>
          )}

          <h2 className="mb-2 mt-8 text-lg font-semibold">What to order</h2>
          {summary.length === 0 ? <Empty message="Nothing ordered yet." /> : (
            <Table headers={['Item', 'Size', 'Quantity', 'Amount']}>
              {summary.map((s, n) => (
                <tr key={n}><td data-label="Item" className="font-medium">{s.item}</td><td data-label="Size">{s.size ?? '—'}</td><td data-label="Quantity">{s.quantity}</td><td data-label="Amount">{formatMoney(s.amount)}</td></tr>
              ))}
            </Table>
          )}

          <h2 className="mb-2 mt-8 text-lg font-semibold">All registrations</h2>
          {registrations.length === 0 ? <Empty message="Nobody has registered yet." /> : (
            <Table headers={['Participant', 'Section', 'Items', 'Total', 'Paid', 'Payment', 'Status', '']}>
              {registrations.map(({ registration: r, participant, isLeader: leader, studentSection, items: lines }) => (
                <tr key={r.id}>
                  <td data-label="Participant" className="font-medium">{participant}</td>
                  <td data-label="Section">{leader ? 'Leader' : studentSection}</td>
                  <td data-label="Items">{lines.map((l) => `${l.itemName}${l.size ? ` ${l.size}` : ''} × ${l.quantity}`).join(', ') || '—'}</td>
                  <td data-label="Total">{formatMoney(r.totalAmount)}</td>
                  <td data-label="Paid">{formatMoney(r.paidAmount)}</td>
                  <td data-label="Payment"><Badge of={FeeStatus} value={r.paymentStatus} /></td>
                  <td data-label="Status"><Badge of={EventRegistrationStatus} value={r.status} /></td>
                  <td className="text-right">
                    {r.status === 'registered' && (
                      <div className="flex justify-end gap-2">
                        <PayButton payable={{ type: 'event_registration', id: r.id, uuid: r.uuid, studentId: r.studentId, userId: r.userId, amountDue: r.totalAmount, paidAmount: r.paidAmount, outstandingAmount: r.outstandingAmount, status: r.paymentStatus, closed: false, description: `Event — ${event.name} (${participant})` }} />
                        {r.paymentStatus === 'Pending' && parseFloat(r.paidAmount) === 0 && <ActionButton action={cancelRegistrationAction} label="Cancel" variant="danger" fields={{ uuid: r.uuid }} />}
                      </div>
                    )}
                  </td>
                </tr>
              ))}
            </Table>
          )}
        </>
      )}
      {payTarget && (
        <OpenPaymentOnLoad target={{ type: 'event_registration', id: payTarget.registration.uuid, amount: payTarget.registration.outstandingAmount, description: `Event — ${event.name} (${payTarget.participant}) — outstanding ${formatMoney(payTarget.registration.outstandingAmount)}` }} />
      )}
      <PaymentModal />
    </>
  );
}
