/**
 * MySQL schema. Table and column names match the original Laravel database,
 * so an existing database can be used as it is.
 */
import {
  bigint,
  boolean,
  char,
  date,
  datetime,
  foreignKey,
  decimal,
  index,
  int,
  json,
  longtext,
  mysqlTable,
  smallint,
  text,
  timestamp,
  uniqueIndex,
  varchar,
  type AnyMySqlColumn,
} from 'drizzle-orm/mysql-core';
import { randomUUID } from 'node:crypto';

const id = () => bigint('id', { mode: 'number', unsigned: true }).primaryKey().autoincrement();
const uuid = () => char('uuid', { length: 36 }).notNull().unique().$defaultFn(() => randomUUID());
const fk = (name: string) => bigint(name, { mode: 'number', unsigned: true });
const money = (name: string) => decimal(name, { precision: 12, scale: 2 });
const stamps = () => ({
  createdAt: timestamp('created_at', { mode: 'date' }),
  updatedAt: timestamp('updated_at', { mode: 'date' }),
});
const softDelete = () => ({ deletedAt: timestamp('deleted_at', { mode: 'date' }) });

export const users = mysqlTable('users', {
  id: id(),
  uuid: uuid(),
  name: varchar('name', { length: 255 }).notNull(),
  nationalId: varchar('national_id', { length: 255 }).notNull().unique(),
  email: varchar('email', { length: 255 }).unique(),
  password: varchar('password', { length: 255 }).notNull(),
  status: varchar('status', { length: 255 }).notNull().default('inactive'),
  studentId: fk('student_id').unique(),
  verifiedAt: timestamp('verified_at', { mode: 'date' }),
  verifiedBy: fk('verified_by'),
  signaturePath: varchar('signature_path', { length: 255 }),
  avatarPath: varchar('avatar_path', { length: 255 }),
  emailNotificationsEnabled: boolean('email_notifications_enabled').notNull().default(true),
  telegramNotificationsEnabled: boolean('telegram_notifications_enabled').notNull().default(false),
  telegramChatId: varchar('telegram_chat_id', { length: 255 }),
  telegramConnectToken: varchar('telegram_connect_token', { length: 64 }),
  telegramConnectTokenExpiresAt: timestamp('telegram_connect_token_expires_at', { mode: 'date' }),
  legacyPinHash: varchar('legacy_pin_hash', { length: 255 }),
  legacyPinSalt: varchar('legacy_pin_salt', { length: 255 }),
  lastLoginAt: timestamp('last_login_at', { mode: 'date' }),
  legacyId: varchar('legacy_id', { length: 255 }),
  rememberToken: varchar('remember_token', { length: 100 }),
  ...stamps(),
  ...softDelete(),
}, (t) => [index('users_status_index').on(t.status), index('users_legacy_id_index').on(t.legacyId)]);

export const userRoles = mysqlTable('user_roles', {
  id: id(),
  userId: fk('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  role: varchar('role', { length: 255 }).notNull(),
  ...stamps(),
}, (t) => [uniqueIndex('user_roles_user_id_role_unique').on(t.userId, t.role)]);

export const userPermissions = mysqlTable('user_permissions', {
  id: id(),
  userId: fk('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  permission: varchar('permission', { length: 255 }).notNull(),
  ...stamps(),
}, (t) => [uniqueIndex('user_permissions_user_id_permission_unique').on(t.userId, t.permission)]);

/** Sign-in sessions. Only a hash of the cookie token is stored. */
export const appSessions = mysqlTable('app_sessions', {
  id: char('id', { length: 64 }).primaryKey(),
  userId: fk('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  ipAddress: varchar('ip_address', { length: 45 }),
  userAgent: varchar('user_agent', { length: 255 }),
  expiresAt: timestamp('expires_at', { mode: 'date' }).notNull(),
  createdAt: timestamp('created_at', { mode: 'date' }),
}, (t) => [index('app_sessions_user_id_index').on(t.userId), index('app_sessions_expires_at_index').on(t.expiresAt)]);

/** Sign-in attempt counters for throttling. */
export const rateLimits = mysqlTable('rate_limits', {
  key: varchar('key', { length: 191 }).primaryKey(),
  hits: int('hits').notNull().default(0),
  resetsAt: timestamp('resets_at', { mode: 'date' }).notNull(),
});

export const students = mysqlTable('students', {
  id: id(),
  uuid: uuid(),
  indexNumber: varchar('index_number', { length: 255 }).notNull().unique(),
  name: varchar('name', { length: 255 }).notNull(),
  nationalId: varchar('national_id', { length: 255 }).notNull().unique(),
  email: varchar('email', { length: 255 }),
  photoPath: varchar('photo_path', { length: 255 }),
  gender: varchar('gender', { length: 255 }),
  permanentAddress: varchar('permanent_address', { length: 255 }),
  presentAddress: varchar('present_address', { length: 255 }),
  dateOfBirth: date('date_of_birth', { mode: 'string' }),
  parentName: varchar('parent_name', { length: 255 }),
  primaryMobile: varchar('primary_mobile', { length: 255 }),
  secondaryMobile: varchar('secondary_mobile', { length: 255 }),
  section: varchar('section', { length: 255 }).notNull(),
  className: varchar('class_name', { length: 255 }),
  patrol: varchar('patrol', { length: 255 }),
  status: varchar('status', { length: 255 }).notNull().default('pending'),
  verifiedAt: timestamp('verified_at', { mode: 'date' }),
  verifiedBy: fk('verified_by').references(() => users.id, { onDelete: 'set null' }),
  legacyId: varchar('legacy_id', { length: 255 }),
  ...stamps(),
  ...softDelete(),
}, (t) => [index('students_section_index').on(t.section), index('students_status_index').on(t.status), index('students_legacy_id_index').on(t.legacyId)]);

export const parentStudentLinks = mysqlTable('parent_student_links', {
  id: id(),
  uuid: uuid(),
  parentUserId: fk('parent_user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  studentId: fk('student_id').notNull().references(() => students.id, { onDelete: 'cascade' }),
  status: varchar('status', { length: 255 }).notNull().default('pending'),
  ...stamps(),
}, (t) => [uniqueIndex('parent_student_links_unique').on(t.parentUserId, t.studentId), index('parent_student_links_status_index').on(t.status)]);

export const groups = mysqlTable('groups', {
  id: id(),
  uuid: uuid(),
  name: varchar('name', { length: 255 }).notNull(),
  type: varchar('type', { length: 255 }),
  section: varchar('section', { length: 255 }),
  ownerId: fk('owner_id').references(() => users.id, { onDelete: 'set null' }),
  status: varchar('status', { length: 255 }).notNull().default('Active'),
  legacyId: varchar('legacy_id', { length: 255 }),
  ...stamps(),
  ...softDelete(),
});

export const groupMembers = mysqlTable('group_members', {
  id: id(),
  groupId: fk('group_id').notNull().references(() => groups.id, { onDelete: 'cascade' }),
  studentId: fk('student_id').notNull().references(() => students.id, { onDelete: 'cascade' }),
  ...stamps(),
}, (t) => [uniqueIndex('group_members_unique').on(t.groupId, t.studentId)]);

export const groupLeaders = mysqlTable('group_leaders', {
  id: id(),
  groupId: fk('group_id').notNull().references(() => groups.id, { onDelete: 'cascade' }),
  userId: fk('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  ...stamps(),
}, (t) => [uniqueIndex('group_leaders_unique').on(t.groupId, t.userId)]);

export const groupAssistantLeaders = mysqlTable('group_assistant_leaders', {
  id: id(),
  groupId: fk('group_id').notNull().references(() => groups.id, { onDelete: 'cascade' }),
  studentId: fk('student_id').notNull().references(() => students.id, { onDelete: 'cascade' }),
  ...stamps(),
}, (t) => [uniqueIndex('group_assistant_leaders_unique').on(t.groupId, t.studentId)]);

export const settings = mysqlTable('settings', {
  id: id(),
  key: varchar('key', { length: 255 }).notNull().unique(),
  value: text('value'),
  updatedBy: fk('updated_by').references(() => users.id, { onDelete: 'set null' }),
  ...stamps(),
});

export const auditLogs = mysqlTable('audit_logs', {
  id: id(),
  uuid: uuid(),
  entityType: varchar('entity_type', { length: 255 }),
  entityId: varchar('entity_id', { length: 255 }),
  action: varchar('action', { length: 255 }).notNull(),
  actorUserId: fk('actor_user_id').references(() => users.id, { onDelete: 'set null' }),
  details: json('details').$type<Record<string, unknown>>(),
  createdAt: timestamp('created_at', { mode: 'date' }),
}, (t) => [index('audit_logs_action_index').on(t.action), index('audit_logs_created_at_index').on(t.createdAt), index('audit_logs_entity_index').on(t.entityType, t.entityId)]);

export const notifications = mysqlTable('notifications', {
  id: char('id', { length: 36 }).primaryKey().$defaultFn(() => randomUUID()),
  type: varchar('type', { length: 255 }).notNull(),
  notifiableType: varchar('notifiable_type', { length: 255 }).notNull(),
  notifiableId: fk('notifiable_id').notNull(),
  data: text('data').notNull(),
  readAt: timestamp('read_at', { mode: 'date' }),
  ...stamps(),
}, (t) => [index('notifications_notifiable_index').on(t.notifiableType, t.notifiableId)]);

export const certificateTemplates = mysqlTable('certificate_templates', {
  id: id(),
  uuid: uuid(),
  templateId: varchar('template_id', { length: 255 }).notNull().unique(),
  name: varchar('name', { length: 255 }).notNull(),
  type: varchar('type', { length: 255 }).notNull(),
  googleSlideId: varchar('google_slide_id', { length: 255 }),
  activityId: fk('activity_id'),
  templateContent: longtext('template_content'),
  active: boolean('active').notNull().default(true),
  ...stamps(),
}, (t) => [index('certificate_templates_type_index').on(t.type)]);

export const activities = mysqlTable('activities', {
  id: id(),
  uuid: uuid(),
  name: varchar('name', { length: 255 }).notNull(),
  date: date('date', { mode: 'string' }).notNull(),
  details: text('details'),
  allStudents: boolean('all_students').notNull().default(false),
  chargeFee: boolean('charge_fee').notNull().default(false),
  feeAmount: money('fee_amount'),
  certificateTemplateId: fk('certificate_template_id').references(() => certificateTemplates.id, { onDelete: 'set null' }),
  createdBy: fk('created_by').references(() => users.id, { onDelete: 'set null' }),
  legacyId: varchar('legacy_id', { length: 255 }),
  ...stamps(),
  ...softDelete(),
}, (t) => [index('activities_date_index').on(t.date)]);

export const activitySections = mysqlTable('activity_sections', {
  id: id(),
  activityId: fk('activity_id').notNull().references(() => activities.id, { onDelete: 'cascade' }),
  section: varchar('section', { length: 255 }).notNull(),
}, (t) => [uniqueIndex('activity_sections_unique').on(t.activityId, t.section)]);

export const activityGroups = mysqlTable('activity_groups', {
  id: id(),
  activityId: fk('activity_id').notNull().references(() => activities.id, { onDelete: 'cascade' }),
  groupId: fk('group_id').notNull().references(() => groups.id, { onDelete: 'cascade' }),
}, (t) => [uniqueIndex('activity_groups_unique').on(t.activityId, t.groupId)]);

export const attendanceRecords = mysqlTable('attendance_records', {
  id: id(),
  uuid: uuid(),
  activityId: fk('activity_id').notNull().references(() => activities.id),
  studentId: fk('student_id').notNull().references(() => students.id),
  status: varchar('status', { length: 255 }).notNull(),
  remarks: varchar('remarks', { length: 255 }),
  markedBy: fk('marked_by').references(() => users.id, { onDelete: 'set null' }),
  markedAt: timestamp('marked_at', { mode: 'date' }),
  ...stamps(),
}, (t) => [uniqueIndex('attendance_records_unique').on(t.activityId, t.studentId), index('attendance_records_status_index').on(t.status)]);

export const roverAttendanceRecords = mysqlTable('rover_attendance_records', {
  id: id(),
  uuid: uuid(),
  activityId: fk('activity_id').notNull().references(() => activities.id),
  studentId: fk('student_id').notNull().references(() => students.id),
  status: varchar('status', { length: 255 }).notNull(),
  isRequired: boolean('is_required').notNull().default(true),
  remarks: varchar('remarks', { length: 255 }),
  markedBy: fk('marked_by').references(() => users.id, { onDelete: 'set null' }),
  markedAt: timestamp('marked_at', { mode: 'date' }),
  ...stamps(),
}, (t) => [uniqueIndex('rover_attendance_records_unique').on(t.activityId, t.studentId)]);

export const classFees = mysqlTable('class_fees', {
  id: id(),
  uuid: uuid(),
  activityId: fk('activity_id').notNull().references(() => activities.id),
  studentId: fk('student_id').notNull().references(() => students.id),
  amount: money('amount').notNull(),
  paidAmount: money('paid_amount').notNull().default('0.00'),
  outstandingAmount: money('outstanding_amount').notNull().default('0.00'),
  status: varchar('status', { length: 255 }).notNull().default('Pending'),
  dueDate: date('due_date', { mode: 'string' }),
  voidedAt: timestamp('voided_at', { mode: 'date' }),
  createdBy: fk('created_by').references(() => users.id, { onDelete: 'set null' }),
  legacyId: varchar('legacy_id', { length: 255 }),
  ...stamps(),
}, (t) => [uniqueIndex('class_fees_unique').on(t.activityId, t.studentId), index('class_fees_status_index').on(t.status)]);

export const annualFeeYears = mysqlTable('annual_fee_years', {
  id: id(),
  uuid: uuid(),
  year: smallint('year', { unsigned: true }).notNull().unique(),
  amount: money('amount').notNull(),
  status: varchar('status', { length: 255 }).notNull().default('Active'),
  createdBy: fk('created_by').references(() => users.id, { onDelete: 'set null' }),
  updatedBy: fk('updated_by').references(() => users.id, { onDelete: 'set null' }),
  ...stamps(),
});

export const annualFees = mysqlTable('annual_fees', {
  id: id(),
  uuid: uuid(),
  annualFeeYearId: fk('annual_fee_year_id').notNull().references(() => annualFeeYears.id),
  studentId: fk('student_id').references(() => students.id),
  userId: fk('user_id').references(() => users.id),
  personType: varchar('person_type', { length: 255 }).notNull(),
  section: varchar('section', { length: 255 }),
  amount: money('amount').notNull(),
  paidAmount: money('paid_amount').notNull().default('0.00'),
  outstandingAmount: money('outstanding_amount').notNull().default('0.00'),
  status: varchar('status', { length: 255 }).notNull().default('Pending'),
  createdBy: fk('created_by').references(() => users.id, { onDelete: 'set null' }),
  legacyId: varchar('legacy_id', { length: 255 }),
  ...stamps(),
}, (t) => [uniqueIndex('annual_fees_student_unique').on(t.annualFeeYearId, t.studentId), uniqueIndex('annual_fees_user_unique').on(t.annualFeeYearId, t.userId), index('annual_fees_status_index').on(t.status)]);

export const shopItems = mysqlTable('shop_items', {
  id: id(),
  uuid: uuid(),
  name: varchar('name', { length: 255 }).notNull(),
  description: text('description'),
  price: money('price').notNull(),
  imagePath: varchar('image_path', { length: 255 }),
  status: varchar('status', { length: 255 }).notNull().default('Active'),
  stockQty: int('stock_qty', { unsigned: true }).notNull().default(0),
  createdBy: fk('created_by').references(() => users.id, { onDelete: 'set null' }),
  legacyId: varchar('legacy_id', { length: 255 }),
  ...stamps(),
  ...softDelete(),
}, (t) => [index('shop_items_status_index').on(t.status)]);

export const purchases = mysqlTable('purchases', {
  id: id(),
  uuid: uuid(),
  studentId: fk('student_id').notNull().references(() => students.id),
  createdBy: fk('created_by').references(() => users.id, { onDelete: 'set null' }),
  totalAmount: money('total_amount').notNull(),
  paidAmount: money('paid_amount').notNull().default('0.00'),
  outstandingAmount: money('outstanding_amount').notNull().default('0.00'),
  paymentStatus: varchar('payment_status', { length: 255 }).notNull().default('Pending'),
  purchaseStatus: varchar('purchase_status', { length: 255 }).notNull().default('PendingPayment'),
  stockDecremented: boolean('stock_decremented').notNull().default(false),
  deliveredBy: fk('delivered_by').references(() => users.id, { onDelete: 'set null' }),
  deliveredAt: timestamp('delivered_at', { mode: 'date' }),
  recipient: varchar('recipient', { length: 255 }),
  legacyId: varchar('legacy_id', { length: 255 }),
  ...stamps(),
}, (t) => [index('purchases_payment_status_index').on(t.paymentStatus), index('purchases_purchase_status_index').on(t.purchaseStatus)]);

export const purchaseItems = mysqlTable('purchase_items', {
  id: id(),
  purchaseId: fk('purchase_id').notNull().references(() => purchases.id),
  shopItemId: fk('shop_item_id').notNull().references(() => shopItems.id),
  itemNameSnapshot: varchar('item_name_snapshot', { length: 255 }).notNull(),
  quantity: int('quantity', { unsigned: true }).notNull(),
  unitPrice: money('unit_price').notNull(),
  totalAmount: money('total_amount').notNull(),
  ...stamps(),
});

export const stockMovements = mysqlTable('stock_movements', {
  id: id(),
  shopItemId: fk('shop_item_id').notNull().references(() => shopItems.id),
  type: varchar('type', { length: 255 }).notNull(),
  quantity: int('quantity', { unsigned: true }).notNull(),
  referenceType: varchar('reference_type', { length: 255 }),
  referenceId: fk('reference_id'),
  actorUserId: fk('actor_user_id').references(() => users.id, { onDelete: 'set null' }),
  ...stamps(),
});

export const payments = mysqlTable('payments', {
  id: id(),
  uuid: uuid(),
  payableType: varchar('payable_type', { length: 255 }).notNull(),
  payableId: fk('payable_id').notNull(),
  studentId: fk('student_id').references(() => students.id),
  amount: money('amount').notNull(),
  method: varchar('method', { length: 255 }).notNull(),
  source: varchar('source', { length: 255 }),
  submittedBy: fk('submitted_by').references(() => users.id, { onDelete: 'set null' }),
  submittedAt: timestamp('submitted_at', { mode: 'date' }),
  acceptedBy: fk('accepted_by').references(() => users.id, { onDelete: 'set null' }),
  status: varchar('status', { length: 255 }).notNull(),
  verifiedBy: fk('verified_by').references(() => users.id, { onDelete: 'set null' }),
  verifiedAt: timestamp('verified_at', { mode: 'date' }),
  rejectionReason: varchar('rejection_reason', { length: 255 }),
  legacyId: varchar('legacy_id', { length: 255 }),
  ...stamps(),
}, (t) => [index('payments_payable_index').on(t.payableType, t.payableId), index('payments_status_index').on(t.status), index('payments_submitted_at_index').on(t.submittedAt)]);

export const paymentProofs = mysqlTable('payment_proofs', {
  id: id(),
  uuid: uuid(),
  paymentId: fk('payment_id').notNull().unique().references(() => payments.id),
  disk: varchar('disk', { length: 255 }).notNull(),
  path: varchar('path', { length: 255 }).notNull(),
  originalFilename: varchar('original_filename', { length: 255 }),
  mimeType: varchar('mime_type', { length: 255 }),
  fileSize: bigint('file_size', { mode: 'number', unsigned: true }),
  uploadedBy: fk('uploaded_by').references(() => users.id, { onDelete: 'set null' }),
  uploadedAt: timestamp('uploaded_at', { mode: 'date' }),
  ...stamps(),
});

export const badges = mysqlTable('badges', {
  id: id(),
  uuid: uuid(),
  badgeId: varchar('badge_id', { length: 255 }).notNull().unique(),
  name: varchar('name', { length: 255 }).notNull(),
  code: varchar('code', { length: 255 }).notNull().unique(),
  section: varchar('section', { length: 255 }),
  description: text('description'),
  category: varchar('category', { length: 255 }).notNull().default('proficiency'),
  imagePath: varchar('image_path', { length: 255 }),
  certificateTemplateId: fk('certificate_template_id').references(() => certificateTemplates.id, { onDelete: 'set null' }),
  numberPrefix: varchar('number_prefix', { length: 255 }),
  ...stamps(),
});

export const badgeRequests = mysqlTable('badge_requests', {
  id: id(),
  uuid: uuid(),
  requestId: varchar('request_id', { length: 255 }).notNull().unique(),
  studentId: fk('student_id').notNull().references(() => students.id),
  studentName: varchar('student_name', { length: 255 }).notNull(),
  badgeId: fk('badge_id').notNull().references(() => badges.id),
  badgeName: varchar('badge_name', { length: 255 }).notNull(),
  status: varchar('status', { length: 255 }).notNull().default('requested'),
  certificateNumber: varchar('certificate_number', { length: 255 }),
  dateAwarded: date('date_awarded', { mode: 'string' }),
  certificatePath: varchar('certificate_path', { length: 255 }),
  requestedBy: fk('requested_by').references(() => users.id, { onDelete: 'set null' }),
  reviewedBy: fk('reviewed_by').references(() => users.id, { onDelete: 'set null' }),
  reviewedAt: timestamp('reviewed_at', { mode: 'date' }),
  reviewNote: varchar('review_note', { length: 255 }),
  generatedBy: fk('generated_by').references(() => users.id, { onDelete: 'set null' }),
  generatedAt: timestamp('generated_at', { mode: 'date' }),
  ...stamps(),
}, (t) => [index('badge_requests_status_index').on(t.status)]);

export const certificateCounters = mysqlTable('certificate_counters', {
  id: id(),
  counterId: varchar('counter_id', { length: 255 }).notNull().unique(),
  badgeId: fk('badge_id').references(() => badges.id, { onDelete: 'set null' }),
  year: smallint('year', { unsigned: true }).notNull(),
  lastNumber: int('last_number', { unsigned: true }).notNull().default(0),
  ...stamps(),
});

export const certificates = mysqlTable('certificates', {
  id: id(),
  uuid: uuid(),
  certId: varchar('cert_id', { length: 255 }).notNull().unique(),
  type: varchar('type', { length: 255 }).notNull(),
  studentId: fk('student_id').notNull().references(() => students.id),
  studentName: varchar('student_name', { length: 255 }).notNull(),
  title: varchar('title', { length: 255 }),
  certNumber: varchar('cert_number', { length: 255 }).notNull().unique(),
  idCardNo: varchar('id_card_no', { length: 255 }),
  dateAwarded: date('date_awarded', { mode: 'string' }).notNull(),
  path: varchar('path', { length: 255 }),
  status: varchar('status', { length: 255 }).notNull().default('issued'),
  badgeId: fk('badge_id').references(() => badges.id),
  badgeName: varchar('badge_name', { length: 255 }),
  templateId: fk('template_id').references(() => certificateTemplates.id),
  activityId: fk('activity_id').references(() => activities.id, { onDelete: 'set null' }),
  badgeRequestId: fk('badge_request_id').references((): AnyMySqlColumn => badgeRequests.id, { onDelete: 'set null' }),
  createdBy: fk('created_by').references(() => users.id, { onDelete: 'set null' }),
  generatedBy: fk('generated_by').references(() => users.id, { onDelete: 'set null' }),
  generatedAt: timestamp('generated_at', { mode: 'date' }),
  verifiedBy: fk('verified_by').references(() => users.id, { onDelete: 'set null' }),
  verifiedAt: timestamp('verified_at', { mode: 'date' }),
  ...stamps(),
}, (t) => [index('certificates_type_index').on(t.type), index('certificates_status_index').on(t.status)]);

export const leadershipRecords = mysqlTable('leadership_records', {
  id: id(),
  uuid: uuid(),
  studentId: fk('student_id').notNull().references(() => students.id),
  patrolOrSix: varchar('patrol_or_six', { length: 255 }).notNull(),
  troopOrGroup: varchar('troop_or_group', { length: 255 }).notNull(),
  startDate: date('start_date', { mode: 'string' }).notNull(),
  endDate: date('end_date', { mode: 'string' }),
  certificateId: fk('certificate_id').references(() => certificates.id, { onDelete: 'set null' }),
  createdBy: fk('created_by').references(() => users.id, { onDelete: 'set null' }),
  updatedBy: fk('updated_by').references(() => users.id, { onDelete: 'set null' }),
  ...stamps(),
});

export const events = mysqlTable('events', {
  id: id(),
  uuid: uuid(),
  name: varchar('name', { length: 255 }).notNull(),
  description: text('description'),
  location: varchar('location', { length: 255 }),
  startsAt: datetime('starts_at', { mode: 'date' }).notNull(),
  endsAt: datetime('ends_at', { mode: 'date' }),
  registrationClosesAt: datetime('registration_closes_at', { mode: 'date' }),
  fee: money('fee').notNull().default('0.00'),
  capacity: int('capacity', { unsigned: true }),
  sections: json('sections').$type<string[] | null>(),
  status: varchar('status', { length: 255 }).notNull().default('draft'),
  createdBy: fk('created_by').references(() => users.id, { onDelete: 'set null' }),
  ...stamps(),
  ...softDelete(),
}, (t) => [index('events_starts_at_index').on(t.startsAt), index('events_status_index').on(t.status)]);

export const eventItems = mysqlTable('event_items', {
  id: id(),
  uuid: uuid(),
  eventId: fk('event_id').notNull().references(() => events.id, { onDelete: 'cascade' }),
  name: varchar('name', { length: 255 }).notNull(),
  description: text('description'),
  price: money('price').notNull().default('0.00'),
  sizes: json('sizes').$type<string[] | null>(),
  sizeChart: json('size_chart').$type<Record<string, string> | null>(),
  sizeGuide: text('size_guide'),
  stock: int('stock', { unsigned: true }),
  maxPerRegistration: int('max_per_registration', { unsigned: true }).notNull().default(5),
  active: boolean('active').notNull().default(true),
  ...stamps(),
});

export const eventRegistrations = mysqlTable('event_registrations', {
  id: id(),
  uuid: uuid(),
  eventId: fk('event_id').notNull().references(() => events.id),
  studentId: fk('student_id').references(() => students.id),
  userId: fk('user_id').references(() => users.id),
  registeredBy: fk('registered_by').references(() => users.id, { onDelete: 'set null' }),
  status: varchar('status', { length: 255 }).notNull().default('registered'),
  paymentOption: varchar('payment_option', { length: 255 }).notNull().default('online'),
  feeAmount: money('fee_amount').notNull().default('0.00'),
  itemsAmount: money('items_amount').notNull().default('0.00'),
  totalAmount: money('total_amount').notNull().default('0.00'),
  paidAmount: money('paid_amount').notNull().default('0.00'),
  outstandingAmount: money('outstanding_amount').notNull().default('0.00'),
  paymentStatus: varchar('payment_status', { length: 255 }).notNull().default('Pending'),
  notes: varchar('notes', { length: 500 }),
  ...stamps(),
}, (t) => [uniqueIndex('event_registrations_student_unique').on(t.eventId, t.studentId), uniqueIndex('event_registrations_user_unique').on(t.eventId, t.userId), index('event_registrations_status_index').on(t.status)]);

export const eventRegistrationItems = mysqlTable('event_registration_items', {
  id: id(),
  eventRegistrationId: fk('event_registration_id').notNull(),
  eventItemId: fk('event_item_id').notNull().references(() => eventItems.id),
  itemName: varchar('item_name', { length: 255 }).notNull(),
  size: varchar('size', { length: 255 }),
  quantity: int('quantity', { unsigned: true }).notNull(),
  unitPrice: money('unit_price').notNull(),
  totalAmount: money('total_amount').notNull(),
  ...stamps(),
}, (t) => [foreignKey({ name: 'event_reg_items_registration_fk', columns: [t.eventRegistrationId], foreignColumns: [eventRegistrations.id] }).onDelete('cascade')]);
