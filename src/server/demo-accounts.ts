/** Sample sign-ins created by the demo seeder (development only). */
export const DEMO_ACCOUNTS = [
  { role: 'Admin', nationalId: (process.env.SCOUT_ADMIN_NATIONAL_ID ?? 'A000001').toUpperCase() },
  { role: 'Leader', nationalId: 'A100001' },
  { role: 'Leader (treasurer)', nationalId: 'A100002' },
  { role: 'Parent', nationalId: 'A100003' },
  { role: 'Scout', nationalId: 'A200001' },
];

export const demoPin = () => process.env.SCOUT_ADMIN_PIN || '123456';
