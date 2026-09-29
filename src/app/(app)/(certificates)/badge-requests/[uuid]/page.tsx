import Link from 'next/link';
import { eq } from 'drizzle-orm';
import { forbidden, notFound } from 'next/navigation';
import { db, schema } from '@/db';
import { Badge } from '@/components/Badge';
import { ConfirmButton } from '@/components/ConfirmButton';
import { Input, Select } from '@/components/form/fields';
import { Card, PageHeader } from '@/components/PageHeader';
import { approveRequestAction, generateRequestAction, rejectRequestAction } from '@/app/actions/certificates';
import { formatDate, formatDateTime, todayLocal } from '@/lib/dates';
import { BadgeRequestStatus } from '@/lib/enums';
import { templatesOfType } from '@/server/certificate-templates';
import { canActOnRequest, canDecideRequest, findRequestByUuid } from '@/server/certificates';
import { requireUser } from '@/server/session';

export const metadata = { title: 'Badge request' };

export default async function BadgeRequestPage({ params }: { params: Promise<{ uuid: string }> }) {
  const user = await requireUser();
  const request = await findRequestByUuid((await params).uuid);
  if (!request) notFound();
  if (!(await canActOnRequest(user, request))) forbidden();
  const decide = await canDecideRequest(user, request);
  const [templates, requester, reviewer, cert] = await Promise.all([
    templatesOfType('badge'),
    request.requestedBy ? db.select({ n: schema.users.name }).from(schema.users).where(eq(schema.users.id, request.requestedBy)).then((r) => r[0]?.n) : null,
    request.reviewedBy ? db.select({ n: schema.users.name }).from(schema.users).where(eq(schema.users.id, request.reviewedBy)).then((r) => r[0]?.n) : null,
    request.certificateNumber ? db.select({ uuid: schema.certificates.uuid }).from(schema.certificates).where(eq(schema.certificates.certNumber, request.certificateNumber)).then((r) => r[0]) : null,
  ]);
  return (
    <>
      <PageHeader title={request.requestId} description={`${request.badgeName} for ${request.studentName}`}>
        {decide && request.status === 'requested' && (
          <>
            <ConfirmButton action={approveRequestAction} label="Approve" variant="accent" size="md" title="Approve request" message="Approve this badge request?" confirm="Approve" fields={{ uuid: request.uuid }}>
              <Input name="note" label="Note (optional)" />
            </ConfirmButton>
            <ConfirmButton action={rejectRequestAction} label="Reject" size="md" title="Reject request" message="Reject this badge request?" confirm="Reject" fields={{ uuid: request.uuid }}>
              <Input name="note" label="Reason (optional)" />
            </ConfirmButton>
          </>
        )}
      </PageHeader>
      <Card className="max-w-2xl space-y-4">
        <dl className="grid grid-cols-2 gap-4 text-sm">
          <div><dt className="text-gray-500">Status</dt><dd><Badge of={BadgeRequestStatus} value={request.status} /></dd></div>
          <div><dt className="text-gray-500">Requested</dt><dd className="font-medium">{formatDateTime(request.createdAt)}{requester ? ` by ${requester}` : ''}</dd></div>
          {reviewer && <div><dt className="text-gray-500">Reviewed by</dt><dd className="font-medium">{reviewer}{request.reviewedAt ? ` on ${formatDateTime(request.reviewedAt)}` : ''}</dd></div>}
          {request.reviewNote && <div><dt className="text-gray-500">Note</dt><dd className="font-medium">{request.reviewNote}</dd></div>}
          {request.certificateNumber && (
            <div><dt className="text-gray-500">Certificate</dt><dd className="font-medium">{cert ? <Link href={`/certificates/${cert.uuid}`} className="hover:underline">{request.certificateNumber}</Link> : request.certificateNumber}{request.dateAwarded ? ` · ${formatDate(request.dateAwarded)}` : ''}</dd></div>
          )}
        </dl>
        {decide && request.status === 'approved' && (
          <form action={generateRequestAction} className="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
            <h2 className="font-semibold">Generate the certificate</h2>
            <input type="hidden" name="uuid" value={request.uuid} />
            <Input name="date_awarded" label="Date awarded" type="date" defaultValue={todayLocal()} required />
            <Select name="template" label="Template" options={templates.map((t) => ({ value: t.uuid, label: t.name }))} placeholder="Badge's own or the active template" />
            <button type="submit" className="btn-primary">Generate</button>
          </form>
        )}
      </Card>
    </>
  );
}
