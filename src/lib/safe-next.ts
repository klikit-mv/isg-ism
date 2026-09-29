/** Only same-site paths, so a link can never send someone to another website. */
export const safeNext = (value: unknown): string => (typeof value === 'string' && /^\/(?!\/)/.test(value) ? value : '/dashboard');
