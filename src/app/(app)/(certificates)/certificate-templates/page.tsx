import { forbidden } from 'next/navigation';
import { desc, isNull } from 'drizzle-orm';
import { db, schema } from '@/db';
import { Badge } from '@/components/Badge';
import { ActionButton, ConfirmButton } from '@/components/ConfirmButton';
import { Empty, PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { deleteTemplateAction, toggleTemplateAction } from '@/app/actions/certificates';
import { CertificateType } from '@/lib/enums';
import { listTemplates } from '@/server/certificate-templates';
import { requireUser } from '@/server/session';
import { isAdmin } from '@/server/users';
import { TemplateModal } from './TemplateModal';

export const metadata = { title: 'Certificate templates' };

export default async function TemplatesPage() {
  const user = await requireUser();
  if (!isAdmin(user)) forbidden();
  const A = schema.activities;
  const [rows, activities] = await Promise.all([listTemplates(), db.select({ uuid: A.uuid, name: A.name }).from(A).where(isNull(A.deletedAt)).orderBy(desc(A.date)).limit(200)]);
  const activityOptions = activities.map((a) => ({ value: a.uuid, label: a.name }));
  const uuidOfActivity = new Map((await db.select({ id: A.id, uuid: A.uuid }).from(A)).map((a) => [a.id, a.uuid]));
  return (
    <>
      <PageHeader title="Certificate templates" description="Choose which certificate style is used for badges, activities and leadership. Only one active template per type is used by default.">
        <TemplateModal activities={activityOptions} />
      </PageHeader>
      {rows.length === 0 ? <Empty message="No templates yet. Add one for each certificate type." /> : (
        <Table headers={['Name', 'Type', 'Activity', 'Used', 'Status', '']}>
          {rows.map(({ template: t, used, activity }) => (
            <tr key={t.id}>
              <td data-label="Name" className="font-medium">{t.name}</td>
              <td data-label="Type"><Badge of={CertificateType} value={t.type} /></td>
              <td data-label="Activity">{activity ?? '—'}</td>
              <td data-label="Used">{used}</td>
              <td data-label="Status">{t.active ? <Badge tone="green">Active</Badge> : <Badge tone="gray">Inactive</Badge>}</td>
              <td className="text-right">
                <div className="flex justify-end gap-2">
                  <TemplateModal activities={activityOptions} template={{ uuid: t.uuid, name: t.name, type: t.type, activityUuid: t.activityId ? uuidOfActivity.get(t.activityId) ?? null : null, active: t.active }} />
                  <ActionButton action={toggleTemplateAction} label={t.active ? 'Deactivate' : 'Activate'} fields={{ uuid: t.uuid }} />
                  <ConfirmButton action={deleteTemplateAction} label="Delete" title="Delete template" message="Delete this template? A template that was used cannot be deleted." confirm="Delete" fields={{ uuid: t.uuid }} />
                </div>
              </td>
            </tr>
          ))}
        </Table>
      )}
    </>
  );
}
