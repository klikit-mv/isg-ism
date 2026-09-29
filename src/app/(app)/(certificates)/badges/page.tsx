import { forbidden } from 'next/navigation';
import { Badge } from '@/components/Badge';
import { FilterInput, FilterSelect, Filters } from '@/components/Filters';
import { currentPage, Pagination } from '@/components/Pagination';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { BADGE_CATEGORIES, ScoutSection } from '@/lib/enums';
import { listBadges } from '@/server/badges';
import { peekBadgeNumber } from '@/server/certificate-numbers';
import { templatesOfType } from '@/server/certificate-templates';
import { mediaUrl } from '@/server/media';
import { requireUser } from '@/server/session';
import { isActive, isAdmin, isLeader } from '@/server/users';
import { BadgeModal, DeleteBadgeButton } from './BadgeModal';

export const metadata = { title: 'Badges' };

export default async function BadgesPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const user = await requireUser();
  if (!isAdmin(user) && !(isActive(user) && isLeader(user))) forbidden();
  const sp = await searchParams;
  const [page, templates] = await Promise.all([listBadges({ q: sp.q, section: sp.section, page: currentPage(sp.page) }), templatesOfType('badge')]);
  const options = templates.map((t) => ({ value: String(t.id), label: t.name }));
  const next = new Map(await Promise.all(page.rows.map(async (b) => [b.id, await peekBadgeNumber(b)] as const)));
  return (
    <>
      <PageHeader title="Badges" description="Badge catalogue and numbering.">
        <BadgeModal templates={options} />
      </PageHeader>
      <Filters action="/badges">
        <FilterInput name="q" label="Search" value={sp.q} placeholder="Name or code" />
        <FilterSelect name="section" label="Section" value={sp.section} options={ScoutSection.options()} placeholder="Any section" />
      </Filters>
      {page.rows.length === 0 ? <Empty message="No badges yet." /> : (
        <>
          <Table headers={['', 'Badge', 'Code', 'Section', 'Category', 'Next number', '']}>
            {page.rows.map((b) => {
              const image = mediaUrl(b.imagePath);
              return (
                <tr key={b.id}>
                  <td className="w-12">{image ? <img src={image} alt="" className="h-10 w-10 rounded object-cover" /> : null}</td>
                  <td data-label="Badge" className="font-medium">{b.name}</td>
                  <td data-label="Code">{b.code}</td>
                  <td data-label="Section">{b.section ? <Badge of={ScoutSection} value={b.section} /> : '—'}</td>
                  <td data-label="Category">{BADGE_CATEGORIES[b.category as keyof typeof BADGE_CATEGORIES] ?? b.category}</td>
                  <td data-label="Next number" className="whitespace-nowrap text-xs">{next.get(b.id)}</td>
                  <td className="text-right">
                    <div className="flex justify-end gap-2">
                      <BadgeModal templates={options} badge={{ uuid: b.uuid, name: b.name, code: b.code, section: b.section, category: b.category, description: b.description, numberPrefix: b.numberPrefix, templateId: b.certificateTemplateId }} />
                      <DeleteBadgeButton uuid={b.uuid} name={b.name} />
                    </div>
                  </td>
                </tr>
              );
            })}
          </Table>
          <Pagination page={page.page} pages={page.pages} path="/badges" query={{ q: sp.q, section: sp.section }} />
        </>
      )}
    </>
  );
}
