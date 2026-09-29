/** Organisation settings from the environment (same names as the old portal). */
export const config = {
  name: process.env.SCOUT_NAME ?? 'Ifthithaah Scout Group',
  shortName: process.env.SCOUT_SHORT_NAME ?? 'Scout Management System',
  organisation: process.env.SCOUT_ORG ?? 'Ifthithaah Scout Group',
  timezone: process.env.SCOUT_TIMEZONE ?? 'Indian/Maldives',
  currency: process.env.SCOUT_CURRENCY ?? 'MVR',
  currencySymbol: process.env.SCOUT_CURRENCY_SYMBOL ?? 'MVR',
  defaultClassFee: process.env.SCOUT_DEFAULT_CLASS_FEE ?? '50.00',
  proofMaxKb: Number(process.env.SCOUT_PROOF_MAX_KB ?? 10240),
  shopImageMaxKb: Number(process.env.SCOUT_SHOP_IMAGE_MAX_KB ?? 5120),
  sessionDays: Number(process.env.SESSION_DAYS ?? 30),
  sessionCookie: 'scout_session',
  isProduction: process.env.NODE_ENV === 'production',
  /** Sample sign-ins are only ever shown outside production. */
  showDemoLogins: process.env.NODE_ENV !== 'production' && process.env.SCOUT_DEMO_LOGINS !== 'false',
  /** Where uploads (photos, proofs, certificates) are stored. */
  storageDir: process.env.STORAGE_DIR ?? './storage-data',
};
