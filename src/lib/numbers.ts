/** True for "12", "12.5" or a finite number; false for "", "abc" or "1e5". */
export const isNumeric = (value: unknown): boolean => (typeof value === 'number' ? Number.isFinite(value) : typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value.trim()));
