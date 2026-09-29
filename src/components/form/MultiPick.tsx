'use client';

import { useMemo, useState } from 'react';

export interface PickOption {
  id: number | string;
  label: string;
  hint?: string;
}

/** Searchable multi-select; each choice is submitted as name[]. */
export function MultiPick({ name, label, options, selected, placeholder = 'Search…' }: { name: string; label?: string; options: PickOption[]; selected: (number | string)[]; placeholder?: string }) {
  const [chosen, setChosen] = useState<string[]>(selected.map(String));
  const [search, setSearch] = useState('');
  const term = search.toLowerCase();
  const filtered = useMemo(
    () => options.filter((o) => !term || `${o.label} ${o.hint ?? ''}`.toLowerCase().includes(term)).slice(0, 50),
    [options, term],
  );
  const toggle = (id: string) => setChosen((c) => (c.includes(id) ? c.filter((x) => x !== id) : [...c, id]));
  const labelFor = (id: string) => options.find((o) => String(o.id) === id)?.label ?? id;

  return (
    <div>
      {label && <span className="label">{label} <span className="font-normal text-gray-400">({chosen.length})</span></span>}
      {chosen.map((id) => <input key={id} type="hidden" name={`${name}[]`} value={id} />)}
      <div className="mb-2 flex flex-wrap gap-1">
        {chosen.map((id) => (
          <span key={id} className="inline-flex items-center gap-1 rounded-full bg-navy-100 px-2.5 py-0.5 text-xs text-navy-800 dark:bg-navy-900 dark:text-navy-100">
            <span>{labelFor(id)}</span>
            <button type="button" onClick={() => toggle(id)} className="font-bold opacity-60 hover:opacity-100" aria-label="Remove">&times;</button>
          </span>
        ))}
      </div>
      <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} className="input" placeholder={placeholder} />
      <div className="mt-1 max-h-56 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
        {filtered.map((o) => (
          <label key={o.id} className="flex cursor-pointer items-center gap-2 border-b border-gray-100 px-3 py-2 text-sm last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
            <input type="checkbox" className="rounded border-gray-300 text-navy-600" checked={chosen.includes(String(o.id))} onChange={() => toggle(String(o.id))} />
            <span>{o.label}</span>
            <span className="ml-auto text-xs text-gray-400">{o.hint}</span>
          </label>
        ))}
        {filtered.length === 0 && <div className="px-3 py-2 text-sm text-gray-500">No matches.</div>}
      </div>
    </div>
  );
}
