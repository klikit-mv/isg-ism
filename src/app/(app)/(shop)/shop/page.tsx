import { Badge } from '@/components/Badge';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { RecordStatus } from '@/lib/enums';
import { formatMoney } from '@/lib/money';
import { mediaUrl } from '@/server/media';
import { buyableStudents, canManageShop, listItems } from '@/server/shop';
import { requireUser } from '@/server/session';
import { isActive } from '@/server/users';
import { BuyButton, DeleteItemButton, ItemFormModal } from './ItemModals';

export const metadata = { title: 'Scout shop' };

export default async function ShopPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  const sp = await searchParams;
  const manager = canManageShop(user);
  const [page, students] = await Promise.all([listItems(user, { q: sp.q, status: sp.status, page: currentPage(sp.page) }), isActive(user) ? buyableStudents(user) : []]);
  return (
    <>
      <PageHeader title="Scout shop" description="Uniforms, badges and supplies. Order for a scout, then pay to confirm.">
        {manager && <ItemFormModal />}
      </PageHeader>
      <Filters action="/shop">
        <FilterInput name="q" label="Search" value={sp.q} placeholder="Item name" />
        {manager && <FilterSelect name="status" label="Status" value={sp.status} options={RecordStatus.options()} placeholder="Any status" />}
      </Filters>
      {page.rows.length === 0 ? <Empty message="No items in the shop yet." /> : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {page.rows.map((item) => {
              const image = mediaUrl(item.imagePath);
              return (
                <div key={item.id} className="card overflow-hidden">
                  {image ? <img src={image} alt={item.name} className="h-40 w-full object-cover" /> : <div className="flex h-40 items-center justify-center bg-gray-100 text-sm text-gray-400 dark:bg-gray-700">No picture</div>}
                  <div className="space-y-2 p-4">
                    <div className="flex items-start justify-between gap-2">
                      <h3 className="font-semibold">{item.name}</h3>
                      <span className="font-semibold text-gold-700 dark:text-gold-400">{formatMoney(item.price)}</span>
                    </div>
                    {item.description && <p className="text-sm text-gray-600 dark:text-gray-300">{item.description}</p>}
                    <div className="flex items-center justify-between text-xs text-gray-500">
                      <span>{item.stockQty > 0 ? `${item.stockQty} in stock` : 'Out of stock'}</span>
                      {manager && <Badge of={RecordStatus} value={item.status} />}
                    </div>
                    <div className="flex flex-wrap gap-2 pt-1">
                      {item.status === 'Active' && students.length > 0 && <BuyButton item={item} students={students} disabled={item.stockQty < 1} />}
                      {manager && <ItemFormModal item={item} />}
                      {manager && <DeleteItemButton uuid={item.uuid} name={item.name} />}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
          <Pagination page={page.page} pages={page.pages} path="/shop" query={{ q: sp.q, status: sp.status }} />
        </>
      )}
    </>
  );
}
