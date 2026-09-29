import Link from 'next/link';
import { Badge } from '@/components/Badge';
import { ActionButton } from '@/components/ConfirmButton';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { cancelRegistrationAction } from '@/app/actions/events';
import { PayButton } from '@/components/finance/PayButton';
import { PaymentModal } from '@/components/finance/PaymentModal';
import { formatDateTime } from '@/lib/dates';
import { EventRegistrationStatus, FeeStatus } from '@/lib/enums';
import { formatMoney } from '@/lib/money';
import { registrationsForUser } from '@/server/events';
import { requireUser } from '@/server/session';

export const metadata = { title: 'Event registrations' };

export default async function MyRegistrationsPage() {
  const user = await requireUser();
  const rows = await registrationsForUser(user);
  return (
    <>
      <PageHeader title="Event registrations" description="Registrations for the scouts you look after, and your own." />
      {rows.length === 0 ? <Empty message="No registrations yet." /> : (
        <Table headers={['Event', 'Participant', 'Items', 'Total', 'Outstanding', 'Payment', 'Status', '']}>
          {rows.map(({ registration: r, eventName, eventUuid, participant, isLeader, items }) => (
            <tr key={r.id}>
              <td data-label="Event" className="font-medium"><Link href={`/events/${eventUuid}`} className="hover:underline">{eventName}</Link><span className="block text-xs text-gray-400">{formatDateTime(r.createdAt)}</span></td>
              <td data-label="Participant">{participant}{isLeader && <span className="text-xs text-gray-400"> (leader)</span>}</td>
              <td data-label="Items">{items.map((i) => `${i.itemName}${i.size ? ` ${i.size}` : ''} × ${i.quantity}`).join(', ') || '—'}</td>
              <td data-label="Total">{formatMoney(r.totalAmount)}</td>
              <td data-label="Outstanding">{formatMoney(r.outstandingAmount)}</td>
              <td data-label="Payment"><Badge of={FeeStatus} value={r.paymentStatus} /></td>
              <td data-label="Status"><Badge of={EventRegistrationStatus} value={r.status} /></td>
              <td className="text-right">
                {r.status === 'registered' && (
                  <div className="flex justify-end gap-2">
                    <PayButton payable={{ type: 'event_registration', id: r.id, uuid: r.uuid, studentId: r.studentId, userId: r.userId, amountDue: r.totalAmount, paidAmount: r.paidAmount, outstandingAmount: r.outstandingAmount, status: r.paymentStatus, closed: false, description: `Event — ${eventName} (${participant})` }} />
                    {r.paymentStatus === 'Pending' && parseFloat(r.paidAmount) === 0 && <ActionButton action={cancelRegistrationAction} label="Cancel" variant="danger" fields={{ uuid: r.uuid }} />}
                  </div>
                )}
              </td>
            </tr>
          ))}
        </Table>
      )}
      <PaymentModal />
    </>
  );
}
