import { Badge } from '@/components/Badge';
import { ActionButton, ConfirmButton } from '@/components/ConfirmButton';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { PayButton } from '@/components/finance/PayButton';
import { PaymentModal } from '@/components/finance/PaymentModal';
import { cancelPurchaseAction, deliverAction, markReadyAction } from '@/app/actions/shop';
import { formatDateTime } from '@/lib/dates';
import { FeeStatus, PurchaseStatus } from '@/lib/enums';
import { formatMoney } from '@/lib/money';
import { canProcessDelivery, listPurchases } from '@/server/purchases';
import { canManageShop } from '@/server/shop';
import { requireUser } from '@/server/session';
import { Input } from '@/components/form/fields';

export const metadata = { title: 'Purchases' };

export default async function PurchasesPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  const sp = await searchParams;
  const page = await listPurchases(user, { q: sp.q, payment_status: sp.payment_status, purchase_status: sp.purchase_status, page: currentPage(sp.page) });
  const staff = canManageShop(user) || canProcessDelivery(user);
  return (
    <>
      <PageHeader title="Purchases" description="Orders from the scout shop." />
      <Filters action="/purchases">
        <FilterInput name="q" label="Search" value={sp.q} placeholder="Scout or item" />
        <FilterSelect name="payment_status" label="Payment" value={sp.payment_status} options={FeeStatus.options().filter((o) => o.value !== 'Void')} placeholder="Any payment status" />
        <FilterSelect name="purchase_status" label="Order" value={sp.purchase_status} options={PurchaseStatus.options()} placeholder="Any order status" />
      </Filters>
      {page.rows.length === 0 ? <Empty message="No purchases yet." /> : (
        <>
          <Table headers={['Ordered', 'Scout', 'Items', 'Total', 'Outstanding', 'Payment', 'Order', '']}>
            {page.rows.map(({ purchase: p, student, lines }) => {
              const open = p.purchaseStatus !== 'Cancelled' && p.purchaseStatus !== 'Delivered';
              return (
                <tr key={p.id}>
                  <td data-label="Ordered" className="whitespace-nowrap">{formatDateTime(p.createdAt)}</td>
                  <td data-label="Scout" className="font-medium">{student}</td>
                  <td data-label="Items">{lines.map((l) => `${l.itemNameSnapshot} × ${l.quantity}`).join(', ')}</td>
                  <td data-label="Total">{formatMoney(p.totalAmount)}</td>
                  <td data-label="Outstanding">{formatMoney(p.outstandingAmount)}</td>
                  <td data-label="Payment"><Badge of={FeeStatus} value={p.paymentStatus} /></td>
                  <td data-label="Order"><Badge of={PurchaseStatus} value={p.purchaseStatus} />{p.recipient && <div className="text-xs text-gray-500">to {p.recipient}</div>}</td>
                  <td className="text-right">
                    <div className="flex flex-wrap justify-end gap-2">
                      {open && (
                        <PayButton payable={{ type: 'purchase', id: p.id, uuid: p.uuid, studentId: p.studentId, userId: null, amountDue: p.totalAmount, paidAmount: p.paidAmount, outstandingAmount: p.outstandingAmount, status: p.paymentStatus, closed: false, description: `Shop purchase — ${lines.map((l) => l.itemNameSnapshot).join(', ')}` }} />
                      )}
                      {staff && p.purchaseStatus === 'Confirmed' && <ActionButton action={markReadyAction} label="Mark ready" fields={{ uuid: p.uuid }} />}
                      {staff && (p.purchaseStatus === 'Confirmed' || p.purchaseStatus === 'ReadyForCollection') && (
                        <ConfirmButton action={deliverAction} label="Deliver" title="Deliver purchase" message="Hand this order over?" confirm="Deliver" variant="primary" fields={{ uuid: p.uuid }}>
                          <Input name="recipient" label="Collected by (optional)" />
                        </ConfirmButton>
                      )}
                      {open && p.paymentStatus === 'Pending' && parseFloat(p.paidAmount) === 0 && (
                        <ConfirmButton action={cancelPurchaseAction} label="Cancel" title="Cancel purchase" message="Cancel this order?" confirm="Cancel order" fields={{ uuid: p.uuid }} />
                      )}
                    </div>
                  </td>
                </tr>
              );
            })}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/purchases" query={{ q: sp.q, payment_status: sp.payment_status, purchase_status: sp.purchase_status }} />
        </>
      )}
      <PaymentModal />
    </>
  );
}
