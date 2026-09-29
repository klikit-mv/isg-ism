/**
 * Enumerations shared by the whole app. Values are stored in the database
 * exactly as before, so existing data keeps working.
 */
export type Tone = 'green' | 'amber' | 'red' | 'blue' | 'purple' | 'gray';

interface EnumDef<V extends string> {
  values: readonly V[];
  /** Display text; defaults to the stored value with underscores as spaces. */
  labels?: Partial<Record<V, string>>;
  tones?: Partial<Record<V, Tone>>;
}

export interface Enum<V extends string> {
  values: readonly V[];
  is(value: unknown): value is V;
  label(value: string | null | undefined): string;
  tone(value: string | null | undefined): Tone;
  options(): { value: V; label: string }[];
  /** Case-insensitive match on value or label, ignoring punctuation. */
  fromLoose(value: string | null | undefined): V | null;
}

const squash = (s: string) => s.toLowerCase().replace(/[^a-z0-9]/g, '');

function defineEnum<V extends string>(def: EnumDef<V>): Enum<V> {
  const label = (value: string | null | undefined) =>
    value == null ? '' : ((def.labels as Record<string, string> | undefined)?.[value] ?? value);
  return {
    values: def.values,
    is: (value: unknown): value is V => typeof value === 'string' && (def.values as readonly string[]).includes(value),
    label,
    tone: (value) => (value == null ? 'gray' : ((def.tones as Record<string, Tone> | undefined)?.[value] ?? 'gray')),
    options: () => def.values.map((value) => ({ value, label: label(value) })),
    fromLoose(value) {
      const needle = squash(value ?? '');
      if (!needle) return null;
      return def.values.find((v) => squash(v) === needle || squash(label(v)) === needle) ?? null;
    },
  };
}

export const Role = defineEnum({
  values: ['admin', 'leader', 'parent', 'student'] as const,
  labels: { admin: 'Admin', leader: 'Leader', parent: 'Parent', student: 'Student' },
  tones: { admin: 'purple', leader: 'blue', parent: 'amber', student: 'green' },
});
export type RoleValue = (typeof Role.values)[number];

export const roleDescriptions: Record<RoleValue, string> = {
  admin: 'Full access, including users, settings, import, audit and section promotion.',
  leader: 'Runs day-to-day operations for the groups they lead; can verify registrations.',
  parent: 'Sees and pays for approved linked children only.',
  student: 'Sees their own record, fees, certificates and shop.',
};

export const Permission = defineEnum({
  values: ['canVerifyPayments', 'canManageShop', 'canProcessDelivery', 'canManageFees'] as const,
  labels: {
    canVerifyPayments: 'Verify payments',
    canManageShop: 'Manage shop',
    canProcessDelivery: 'Process delivery',
    canManageFees: 'Manage annual fees',
  },
});
export type PermissionValue = (typeof Permission.values)[number];

export const UserStatus = defineEnum({
  values: ['active', 'inactive'] as const,
  labels: { active: 'Active', inactive: 'Inactive' },
  tones: { active: 'green', inactive: 'gray' },
});
export const StudentStatus = defineEnum({
  values: ['active', 'pending', 'inactive'] as const,
  labels: { active: 'Active', pending: 'Pending', inactive: 'Inactive' },
  tones: { active: 'green', pending: 'amber', inactive: 'gray' },
});
export const RecordStatus = defineEnum({
  values: ['Active', 'Inactive'] as const,
  tones: { Active: 'green', Inactive: 'gray' },
});
export const ShopItemStatus = RecordStatus;

export const Gender = defineEnum({ values: ['Male', 'Female'] as const });

export const ScoutSection = defineEnum({
  values: ['Pre Cub', 'Cub Scout', 'Scout', 'Rover'] as const,
  tones: { 'Pre Cub': 'amber', 'Cub Scout': 'blue', Scout: 'green', Rover: 'purple' },
});
export type ScoutSectionValue = (typeof ScoutSection.values)[number];

export const sectionNext = (s: ScoutSectionValue): ScoutSectionValue | null =>
  ({ 'Pre Cub': 'Cub Scout', 'Cub Scout': 'Scout', Scout: 'Rover', Rover: null } as const)[s];
export const sectionNumberPrefix = (s: ScoutSectionValue): string =>
  ({ 'Pre Cub': 'PRECUB', 'Cub Scout': 'CUB', Scout: 'SCOUT', Rover: 'ROVER' } as const)[s];

export const ParentLinkStatus = defineEnum({
  values: ['pending', 'approved', 'rejected', 'inactive'] as const,
  labels: { pending: 'Pending', approved: 'Approved', rejected: 'Rejected', inactive: 'Inactive' },
  tones: { approved: 'green', pending: 'amber', rejected: 'red', inactive: 'gray' },
});

export const AttendanceStatus = defineEnum({
  values: ['Present', 'Late', 'Absent', 'Excused'] as const,
  tones: { Present: 'green', Late: 'amber', Absent: 'red', Excused: 'blue' },
});
export const RoverAttendanceStatus = defineEnum({
  values: ['Present', 'Absent', 'Excused'] as const,
  tones: { Present: 'green', Absent: 'red', Excused: 'blue' },
});

export const FeeStatus = defineEnum({
  values: ['Pending', 'Partial', 'AwaitingVerification', 'Paid', 'Void'] as const,
  labels: { AwaitingVerification: 'Awaiting verification' },
  tones: { Paid: 'green', Partial: 'blue', AwaitingVerification: 'amber', Pending: 'red', Void: 'gray' },
});
export type FeeStatusValue = (typeof FeeStatus.values)[number];

export const PaymentStatus = defineEnum({
  values: ['Pending', 'AwaitingVerification', 'Paid', 'Rejected'] as const,
  labels: { AwaitingVerification: 'Awaiting verification', Paid: 'Approved' },
  tones: { Paid: 'green', AwaitingVerification: 'amber', Pending: 'gray', Rejected: 'red' },
});
export const PaymentMethod = defineEnum({
  values: ['cash', 'online'] as const,
  labels: { cash: 'Cash', online: 'Online transfer' },
});
export type PaymentMethodValue = (typeof PaymentMethod.values)[number];

export const PersonType = defineEnum({
  values: ['Student', 'Leader'] as const,
  labels: { Student: 'Scout', Leader: 'Leader' },
});

export const PurchaseStatus = defineEnum({
  values: ['PendingPayment', 'PaymentVerification', 'Confirmed', 'ReadyForCollection', 'Delivered', 'Cancelled'] as const,
  labels: { PendingPayment: 'Pending payment', PaymentVerification: 'Payment verification', ReadyForCollection: 'Ready for collection' },
  tones: { PendingPayment: 'red', PaymentVerification: 'amber', Confirmed: 'blue', ReadyForCollection: 'purple', Delivered: 'green', Cancelled: 'gray' },
});

export const BadgeRequestStatus = defineEnum({
  values: ['requested', 'approved', 'rejected', 'generated'] as const,
  labels: { requested: 'Requested', approved: 'Approved', rejected: 'Rejected', generated: 'Generated' },
  tones: { requested: 'amber', approved: 'blue', rejected: 'red', generated: 'green' },
});
export const CertificateStatus = defineEnum({
  values: ['issued', 'verified'] as const,
  labels: { issued: 'Issued', verified: 'Verified' },
  tones: { issued: 'blue', verified: 'green' },
});
export const CertificateType = defineEnum({
  values: ['badge', 'general', 'leadership'] as const,
  labels: { badge: 'Badge', general: 'General', leadership: 'Leadership' },
  tones: { badge: 'green', general: 'blue', leadership: 'purple' },
});

export const EventStatus = defineEnum({
  values: ['draft', 'open', 'closed', 'cancelled'] as const,
  labels: { draft: 'Draft (not visible)', open: 'Open for registration', closed: 'Registration closed', cancelled: 'Cancelled' },
  tones: { draft: 'gray', open: 'green', closed: 'amber', cancelled: 'red' },
});
export type EventStatusValue = (typeof EventStatus.values)[number];
export const EventRegistrationStatus = defineEnum({
  values: ['registered', 'cancelled'] as const,
  labels: { registered: 'Registered', cancelled: 'Cancelled' },
  tones: { registered: 'green', cancelled: 'gray' },
});

export const BADGE_CATEGORIES = {
  proficiency: 'Proficiency',
  special: 'Special',
  event: 'Event',
  other: 'Other',
} as const;
