import { getPool } from '@/db';
import { AttendanceStatus, FeeStatus, Gender, PaymentMethod, PaymentStatus, PersonType, PurchaseStatus, RecordStatus, RoverAttendanceStatus, ScoutSection, StudentStatus, UserStatus } from '@/lib/enums';
import { buildWorkbook, type Dropdowns } from './xlsx';

/** Raw exports of every ledger, for backups and moving data; columns keep their database names. */
const LEDGERS = {
  students: ['students', ['uuid', 'index_number', 'name', 'national_id', 'email', 'gender', 'date_of_birth', 'section', 'class_name', 'patrol', 'status', 'parent_name', 'primary_mobile', 'secondary_mobile', 'permanent_address', 'present_address', 'created_at']],
  users: ['users', ['uuid', 'name', 'national_id', 'email', 'status', 'last_login_at', 'created_at']],
  groups: ['groups', ['uuid', 'name', 'type', 'section', 'status', 'created_at']],
  activities: ['activities', ['uuid', 'name', 'date', 'all_students', 'charge_fee', 'fee_amount', 'created_at']],
  attendance: ['attendance_records', ['uuid', 'activity_id', 'student_id', 'status', 'remarks', 'marked_by', 'marked_at']],
  'rover-attendance': ['rover_attendance_records', ['uuid', 'activity_id', 'student_id', 'status', 'is_required', 'marked_by', 'marked_at']],
  'class-fees': ['class_fees', ['uuid', 'activity_id', 'student_id', 'amount', 'paid_amount', 'outstanding_amount', 'status', 'due_date', 'voided_at']],
  'annual-fees': ['annual_fees', ['uuid', 'annual_fee_year_id', 'student_id', 'user_id', 'person_type', 'section', 'amount', 'paid_amount', 'outstanding_amount', 'status']],
  payments: ['payments', ['uuid', 'payable_type', 'payable_id', 'student_id', 'amount', 'method', 'source', 'status', 'submitted_by', 'submitted_at', 'verified_by', 'verified_at', 'rejection_reason']],
  'payment-proofs': ['payment_proofs', ['uuid', 'payment_id', 'disk', 'path', 'original_filename', 'mime_type', 'file_size', 'uploaded_at']],
  'shop-items': ['shop_items', ['uuid', 'name', 'price', 'stock_qty', 'status', 'created_at']],
  purchases: ['purchases', ['uuid', 'student_id', 'total_amount', 'paid_amount', 'outstanding_amount', 'payment_status', 'purchase_status', 'stock_decremented', 'delivered_at', 'recipient', 'created_at']],
  'audit-logs': ['audit_logs', ['id', 'action', 'entity_type', 'entity_id', 'actor_user_id', 'details', 'created_at']],
} as const;

export type LedgerType = keyof typeof LEDGERS;
export const LEDGER_TYPES = Object.keys(LEDGERS) as LedgerType[];
export const isLedger = (t: string): t is LedgerType => t in LEDGERS;

function dropdowns(type: LedgerType): Dropdowns {
  const status: readonly string[] | null = ({
    students: StudentStatus.values, users: UserStatus.values, groups: RecordStatus.values, attendance: AttendanceStatus.values, 'rover-attendance': RoverAttendanceStatus.values,
    'class-fees': FeeStatus.values, 'annual-fees': FeeStatus.values, payments: PaymentStatus.values, 'shop-items': RecordStatus.values,
  } as Record<string, readonly string[]>)[type] ?? null;
  const all: Dropdowns = {
    ...(status ? { status } : {}), gender: Gender.values, section: ScoutSection.values, method: PaymentMethod.values, person_type: PersonType.values,
    payment_status: FeeStatus.values, purchase_status: PurchaseStatus.values, payable_type: ['class_fee', 'annual_fee', 'purchase', 'event_registration'],
  };
  return all;
}

const cell = (v: unknown): string | number | boolean | Date | null =>
  v === null || v === undefined ? null : v instanceof Date || typeof v === 'number' || typeof v === 'string' || typeof v === 'boolean' ? v : Buffer.isBuffer(v) ? v.toString('hex') : JSON.stringify(v);

export async function exportLedger(type: LedgerType, format: 'xlsx' | 'csv' = 'xlsx'): Promise<Buffer> {
  const [table, columns] = LEDGERS[type];
  const cols = columns.map((c) => `\`${c}\``).join(', ');
  const order = type === 'audit-logs' ? 'ORDER BY id DESC LIMIT 5000' : 'ORDER BY id';
  const [rows] = await getPool().query<any[]>(`SELECT ${cols} FROM \`${table}\` ${order}`);
  return buildWorkbook([{ name: type, headings: [...columns], rows: rows.map((r) => columns.map((c) => cell(r[c]))), dropdowns: dropdowns(type) }], format);
}
