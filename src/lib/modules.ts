import { Permission, Role, type PermissionValue, type RoleValue } from './enums';

export interface NavItem {
  label: string;
  href: string;
  roles?: RoleValue[];
  permission?: PermissionValue;
  /** Path prefixes that mark this item active. Defaults to `href`. */
  match?: string[];
  /** Match only the exact path (for index pages that share a prefix with others). */
  exact?: boolean;
}

export interface ScoutModule {
  key: string;
  title: string;
  description: string;
  icon: string;
  home: string;
  roles: RoleValue[];
  /** Path prefixes that belong to the module. */
  paths: string[];
  nav: NavItem[];
}

const ALL: RoleValue[] = ['admin', 'leader', 'parent', 'student'];

/** Static module definitions for the hub, sidebar and route guard. */
export const MODULES: ScoutModule[] = [
  {
    key: 'operations', title: 'Scout operations', description: 'Scouts, groups, activities and attendance.', icon: 'users', home: '/students',
    roles: ['admin', 'leader'],
    paths: ['/students', '/parent-registrations', '/groups', '/activities', '/attendance', '/rover-attendance'],
    nav: [
      { label: 'Students', href: '/students', match: ['/students'] },
      { label: 'Section promotion', href: '/students/promote', roles: ['admin'] },
      { label: 'Parent registrations', href: '/parent-registrations' },
      { label: 'Groups', href: '/groups' },
      { label: 'Activities', href: '/activities' },
      { label: 'Mark attendance', href: '/attendance' },
      { label: 'Rover attendance', href: '/rover-attendance' },
    ],
  },
  {
    key: 'family', title: 'Family', description: 'Your children’s records and attendance.', icon: 'home', home: '/family',
    roles: ['parent'], paths: ['/family'],
    nav: [
      { label: 'My students', href: '/family', match: ['/family/students'], exact: true },
      { label: 'Attendance', href: '/family/attendance' },
    ],
  },
  {
    key: 'self', title: 'My record', description: 'Your details, attendance and fees.', icon: 'id', home: '/me',
    roles: ['student'], paths: ['/me'],
    nav: [
      { label: 'My details', href: '/me', match: ['/me/certificates', '/me/badge-requests', '/me/leadership'], exact: true },
      { label: 'My attendance', href: '/me/attendance' },
      { label: 'My fees', href: '/me/fees' },
    ],
  },
  {
    key: 'certificates', title: 'Certificates', description: 'Certificates, badges and leadership records.', icon: 'award', home: '/certificates',
    roles: ALL, paths: ['/certificates', '/leadership', '/badge-requests', '/badges', '/certificate-templates'],
    nav: [
      { label: 'Certificates', href: '/certificates', exact: true, match: ['/certificates/create', '/certificates/bulk-create'] },
      { label: 'Leadership', href: '/leadership' },
      { label: 'Badge requests', href: '/badge-requests' },
      { label: 'Badges', href: '/badges', roles: ['admin', 'leader'] },
      { label: 'Templates', href: '/certificate-templates', roles: ['admin'] },
      { label: 'Verify certificate', href: '/certificates/verify' },
    ],
  },
  {
    key: 'finance', title: 'Fees and payments', description: 'Class fees, annual fees and payments.', icon: 'wallet', home: '/class-fees',
    roles: ALL, paths: ['/class-fees', '/annual-fees', '/payments', '/payment-verification'],
    nav: [
      { label: 'Class fees', href: '/class-fees' },
      { label: 'Annual fees', href: '/annual-fees' },
      { label: 'Payments', href: '/payments' },
      { label: 'Verification', href: '/payment-verification', permission: 'canVerifyPayments' },
    ],
  },
  {
    key: 'shop', title: 'Scout shop', description: 'Uniforms, badges and supplies.', icon: 'bag', home: '/shop',
    roles: ALL, paths: ['/shop', '/purchases'],
    nav: [
      { label: 'Shop', href: '/shop' },
      { label: 'Purchases', href: '/purchases' },
    ],
  },
  {
    key: 'events', title: 'Events', description: 'Camps and events: registration, pre-orders and payments.', icon: 'calendar', home: '/events',
    roles: ALL, paths: ['/events'],
    nav: [
      { label: 'Events', href: '/events', exact: true, match: ['/events/create'] },
      { label: 'Registrations', href: '/events/registrations' },
    ],
  },
  {
    key: 'reports', title: 'Reports', description: 'Attendance, fees, payments and shop reports.', icon: 'chart', home: '/reports',
    roles: ['admin', 'leader'], paths: ['/reports'],
    nav: [{ label: 'Reports', href: '/reports' }],
  },
  {
    key: 'administration', title: 'Administration', description: 'Users, parent links, settings, audit and import.', icon: 'cog', home: '/users',
    roles: ['admin'], paths: ['/users', '/parent-links', '/settings', '/audit-logs', '/import'],
    nav: [
      { label: 'Users', href: '/users' },
      { label: 'Parent links', href: '/parent-links' },
      { label: 'Settings', href: '/settings' },
      { label: 'Audit logs', href: '/audit-logs' },
      { label: 'Import', href: '/import' },
    ],
  },
];

/** Pages of the signed-in person, outside any module. */
export const PERSONAL_PATHS = ['/profile', '/notifications'];
export const PERSONAL_NAV: NavItem[] = [
  { label: 'My profile', href: '/profile' },
  { label: 'Notifications', href: '/notifications' },
];

/** Paths any signed-in user may open whatever module they are in. */
export const ALWAYS_OPEN = ['/dashboard', '/profile', '/notifications', '/certificates/verify', '/media', '/photos'];

export const findModule = (key: string) => MODULES.find((m) => m.key === key) ?? null;

const under = (path: string, prefix: string) => path === prefix || path.startsWith(prefix + '/');

/** The longest matching path prefix wins ("/events/registrations" is still Events). */
export function moduleForPath(path: string): ScoutModule | null {
  let best: { module: ScoutModule; length: number } | null = null;
  for (const module of MODULES) {
    for (const prefix of module.paths) {
      if (under(path, prefix) && (!best || prefix.length > best.length)) best = { module, length: prefix.length };
    }
  }
  return best?.module ?? null;
}

export const isPersonalPath = (path: string) => PERSONAL_PATHS.some((p) => under(path, p));
export const isAlwaysOpen = (path: string) => ALWAYS_OPEN.some((p) => under(path, p));

interface Person {
  status: string;
  roles: RoleValue[];
  permissions: PermissionValue[];
}
const isAdmin = (u: Person) => u.status === 'active' && u.roles.includes('admin');
const hasPermission = (u: Person, p: PermissionValue) => isAdmin(u) || u.permissions.includes(p);

export function canOpen(user: Person, module: ScoutModule): boolean {
  return user.status === 'active' && module.roles.some((r) => user.roles.includes(r));
}

export const modulesFor = (user: Person) => MODULES.filter((m) => canOpen(user, m));

export interface NavLink {
  label: string;
  href: string;
  match: string[];
  exact: boolean;
}

/** Sidebar links of a module the person may see. */
export function navFor(user: Person, module: ScoutModule): NavLink[] {
  return module.nav
    .filter((item) => !item.roles || item.roles.some((r) => user.roles.includes(r)))
    .filter((item) => !item.permission || hasPermission(user, item.permission))
    .map((item) => ({ label: item.label, href: item.href, match: item.match ?? [item.href], exact: item.exact ?? false }));
}

/** Is a sidebar link active for this path? Longer, more specific links win. */
export function activeLink(links: NavLink[], path: string): NavLink | null {
  let best: { link: NavLink; length: number } | null = null;
  for (const link of links) {
    const prefixes = [link.href, ...link.match];
    for (const prefix of prefixes) {
      const hit = link.exact && prefix === link.href ? path === prefix : under(path, prefix);
      if (hit && (!best || prefix.length > best.length)) best = { link, length: prefix.length };
    }
  }
  return best?.link ?? null;
}

void Role; void Permission;
