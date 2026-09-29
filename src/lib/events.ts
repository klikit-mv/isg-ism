/** Pure event rules shared by server and client code. */

const clean = (sections: readonly string[] | null | undefined) => (sections ?? []).filter(Boolean);

/** Rovers may join any event; other scouts only the sections it is open to. */
export function isOpenForSection(sections: readonly string[] | null | undefined, section: string): boolean {
  const list = clean(sections);
  return list.length === 0 || section === 'Rover' || list.includes(section);
}

export function sectionsLabel(sections: readonly string[] | null | undefined): string {
  const list = clean(sections);
  return list.length === 0 ? 'All sections' : list.join(', ');
}

/** Who may register, as shown to members. */
export function audienceLabel(sections: readonly string[] | null | undefined): string {
  const list = clean(sections);
  if (list.length === 0) return 'All sections, leaders and rovers';
  return `${[...list.filter((s) => s !== 'Rover'), 'Leaders'].join(', ')} and Rovers`;
}

/** Open, and the registration deadline (if any) has not passed. */
export function acceptsRegistrations(event: { status: string; registrationClosesAt: Date | null }, now = new Date()): boolean {
  return event.status === 'open' && (event.registrationClosesAt === null || event.registrationClosesAt.getTime() > now.getTime());
}

/**
 * Parse sizes with optional measurements. Accepts "S, M, L" or one size per line
 * such as "M: Chest 38 in, Length 28 in".
 */
export function parseSizes(value: string | null | undefined): { sizes: string[] | null; chart: Record<string, string> | null } {
  const text = (value ?? '').replace(/\r/g, '');
  const multiLine = text.includes('\n') || text.includes(':');
  const sizes: string[] = [];
  const chart: Record<string, string> = {};
  for (const entry of text.split(multiLine ? /[\n;]+/ : /[,;]+/)) {
    const at = entry.indexOf(':');
    const size = (at === -1 ? entry : entry.slice(0, at)).trim();
    const measurement = at === -1 ? '' : entry.slice(at + 1).trim();
    if (!size || sizes.includes(size)) continue;
    sizes.push(size);
    if (measurement) chart[size] = measurement;
  }
  return { sizes: sizes.length ? sizes : null, chart: Object.keys(chart).length ? chart : null };
}

interface SizedItem { sizes: string[] | null; sizeChart: Record<string, string> | null }

export const sizeList = (item: SizedItem): string[] => (item.sizes ?? []).map((s) => s.trim()).filter(Boolean);

/** Measurements per size, e.g. { M: 'Chest 38 in' }. */
export function measurements(item: SizedItem): Record<string, string> {
  const out: Record<string, string> = {};
  for (const size of sizeList(item)) {
    const m = (item.sizeChart?.[size] ?? '').trim();
    if (m) out[size] = m;
  }
  return out;
}

export function sizeLabel(item: SizedItem, size: string): string {
  const m = measurements(item)[size];
  return m ? `${size} (${m})` : size;
}

/** The sizes as typed in the item form: one per line, "size: measurements". */
export function sizesText(item: SizedItem): string {
  const chart = measurements(item);
  if (Object.keys(chart).length === 0) return sizeList(item).join(', ');
  return sizeList(item).map((s) => (chart[s] ? `${s}: ${chart[s]}` : s)).join('\n');
}
