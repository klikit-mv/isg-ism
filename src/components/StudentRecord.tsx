import Link from 'next/link';
import { Badge } from './Badge';
import { ConfirmButton, ActionButton } from './ConfirmButton';
import { Empty } from './PageHeader';
import { Pagination } from './Pagination';
import { StudentAvatar } from './StudentAvatar';
import { Table } from './Table';
import { PhotoForm } from './PhotoForm';
import { BadgeRequestStatus, CertificateStatus, CertificateType, Gender, ScoutSection, StudentStatus } from '@/lib/enums';
import { formatDate, formatDateTime } from '@/lib/dates';
import { deleteStudentAction, rejectStudentAction, verifyStudentAction } from '@/app/actions/students';
import { canChangePhoto, canManageStudents, canVerifyRegistrations } from '@/server/policies';
import { assignedParent, badgeRequestsOf, certificatesOf, groupNamesFor, leadershipOf, type StudentRow } from '@/server/student-queries';
import type { AuthUser } from '@/server/users';

export type RecordContext = 'students' | 'family' | 'self';
export const RECORD_TABS = { profile: 'Profile', certificates: 'Certificates', 'badge-requests': 'Badge requests', leadership: 'Leadership' } as const;
export type RecordTab = keyof typeof RECORD_TABS;

const tabUrl = (context: RecordContext, student: StudentRow, tab: RecordTab): string => {
  const suffix = tab === 'profile' ? '' : `/${tab}`;
  if (context === 'self') return `/me${suffix}`;
  if (context === 'family') return tab === 'profile' ? `/family/students/${student.uuid}` : `/family/students/${student.uuid}${suffix}`;
  return `/students/${student.uuid}${suffix}`;
};

/** The four-tab scout record, shared by staff, family and self pages. */
export async function StudentRecord({ viewer, student, tab, context, page = 1 }: { viewer: AuthUser; student: StudentRow; tab: RecordTab; context: RecordContext; page?: number }) {
  const isPending = student.status === 'pending';
  const path = tabUrl(context, student, tab);

  return (
    <>
      <div className="card mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <StudentAvatar student={student} className="h-20 w-20 text-xl" />
        <div className="flex-1">
          <h1 className="text-2xl font-bold">{student.name}</h1>
          <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span>{student.indexNumber}</span><span>·</span><span>{student.nationalId}</span>
            <Badge of={ScoutSection} value={student.section} />
            <Badge of={StudentStatus} value={student.status} />
          </div>
        </div>
        {context === 'students' && (
          <div className="flex flex-wrap gap-2">
            {isPending && canVerifyRegistrations(viewer) && (
              <>
                <ActionButton action={verifyStudentAction} label="Verify" variant="accent" size="md" fields={{ uuid: student.uuid }} />
                <ConfirmButton action={rejectStudentAction} fields={{ uuid: student.uuid }} label="Decline" size="md" message="Decline this registration? The account stays inactive." confirm="Decline" />
              </>
            )}
            {canManageStudents(viewer) && (
              <>
                <Link href={`/students/${student.uuid}/edit`} className="btn-secondary">Edit</Link>
                <ConfirmButton action={deleteStudentAction} fields={{ uuid: student.uuid }} label="Delete" size="md" message={`Delete ${student.name}? Their history is kept, but the scout and account are removed from lists.`} confirm="Delete" />
              </>
            )}
          </div>
        )}
      </div>

      <nav className="mb-6 flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-700" aria-label="Record tabs">
        {(Object.keys(RECORD_TABS) as RecordTab[]).map((key) => (
          <Link key={key} href={tabUrl(context, student, key)} className={`-mb-px whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium ${tab === key ? 'border-navy-600 text-navy-700 dark:border-navy-400 dark:text-navy-200' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'}`}>
            {RECORD_TABS[key]}
          </Link>
        ))}
      </nav>

      {tab === 'profile' && <ProfileTab viewer={viewer} student={student} context={context} />}
      {tab === 'certificates' && <CertificatesTab student={student} page={page} path={path} />}
      {tab === 'badge-requests' && <BadgeRequestsTab student={student} page={page} path={path} />}
      {tab === 'leadership' && <LeadershipTab student={student} page={page} path={path} />}
    </>
  );
}

async function ProfileTab({ viewer, student, context }: { viewer: AuthUser; student: StudentRow; context: RecordContext }) {
  const [groups, parent] = await Promise.all([groupNamesFor(student.id), assignedParent(student.id)]);
  const facts: [string, string | null | undefined][] = [
    ['Email', student.email], ['Gender', student.gender ? Gender.label(student.gender) : null], ['Date of birth', formatDate(student.dateOfBirth)],
    ['Parent name', student.parentName], ['Primary mobile', student.primaryMobile], ['Secondary mobile', student.secondaryMobile],
    ['Permanent address', student.permanentAddress], ['Present address', student.presentAddress], ['Class', student.className], ['Patrol', student.patrol],
    ['Groups', groups.join(', ')], ['Linked parent', parent?.name], ['Verified', student.verifiedAt ? formatDateTime(student.verifiedAt) : null],
  ];
  const photo = context === 'students' && (await canChangePhoto(viewer, student));
  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <div className="card lg:col-span-2">
        <dl className="grid gap-4 sm:grid-cols-2">
          {facts.map(([label, value]) => (
            <div key={label}>
              <dt className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</dt>
              <dd className="mt-1 text-sm">{value || '—'}</dd>
            </div>
          ))}
        </dl>
      </div>
      {photo && <PhotoForm uuid={student.uuid} hasPhoto={!!student.photoPath} />}
    </div>
  );
}

async function CertificatesTab({ student, page, path }: { student: StudentRow; page: number; path: string }) {
  const data = await certificatesOf(student.id, page);
  if (data.rows.length === 0) return <Empty message="No certificates yet." />;
  return (
    <>
      <Table headers={['Number', 'Certificate', 'Type', 'Awarded', 'Status', '']}>
        {data.rows.map((c) => (
          <tr key={c.id}>
            <td data-label="Number" className="font-mono text-xs">{c.certNumber}</td>
            <td data-label="Certificate">{c.title || c.badgeName || 'Certificate'}</td>
            <td data-label="Type"><Badge of={CertificateType} value={c.type} /></td>
            <td data-label="Awarded">{formatDate(c.dateAwarded)}</td>
            <td data-label="Status"><Badge of={CertificateStatus} value={c.status} /></td>
            <td className="whitespace-nowrap text-right">
              <Link href={`/certificates/${c.uuid}`} className="link">View</Link>
              <a href={`/certificates/${c.uuid}/download`} className="link ml-2">PDF</a>
            </td>
          </tr>
        ))}
      </Table>
      <Pagination page={data.page} pages={data.pages} path={path} query={{}} />
    </>
  );
}

async function BadgeRequestsTab({ student, page, path }: { student: StudentRow; page: number; path: string }) {
  const data = await badgeRequestsOf(student.id, page);
  return (
    <>
      <div className="mb-4"><Link href={`/badge-requests/create?student=${student.uuid}`} className="btn-primary btn-sm">Request a badge</Link></div>
      {data.rows.length === 0 ? <Empty message="No badge requests yet." /> : (
        <>
          <Table headers={['Request', 'Badge', 'Status', 'Certificate', 'Requested']}>
            {data.rows.map((r) => (
              <tr key={r.id}>
                <td data-label="Request" className="font-mono text-xs"><Link className="link" href={`/badge-requests/${r.uuid}`}>{r.requestId}</Link></td>
                <td data-label="Badge">{r.badgeName}</td>
                <td data-label="Status"><Badge of={BadgeRequestStatus} value={r.status} /></td>
                <td data-label="Certificate" className="font-mono text-xs">{r.certificateNumber || '—'}</td>
                <td data-label="Requested">{formatDate(r.createdAt)}</td>
              </tr>
            ))}
          </Table>
          <Pagination page={data.page} pages={data.pages} path={path} query={{}} />
        </>
      )}
    </>
  );
}

async function LeadershipTab({ student, page, path }: { student: StudentRow; page: number; path: string }) {
  const data = await leadershipOf(student.id, page);
  if (data.rows.length === 0) return <Empty message="No records." />;
  return (
    <>
      <Table headers={['Patrol or six', 'Troop or group', 'Start', 'End', 'Certificate']}>
        {data.rows.map(({ record, certNumber }) => (
          <tr key={record.id}>
            <td data-label="Patrol or six"><Link className="link" href={`/leadership/${record.uuid}`}>{record.patrolOrSix}</Link></td>
            <td data-label="Troop or group">{record.troopOrGroup}</td>
            <td data-label="Start">{formatDate(record.startDate)}</td>
            <td data-label="End">{formatDate(record.endDate) || '—'}</td>
            <td data-label="Certificate" className="font-mono text-xs">{certNumber ?? '—'}</td>
          </tr>
        ))}
      </Table>
      <Pagination page={data.page} pages={data.pages} path={path} query={{}} />
    </>
  );
}
