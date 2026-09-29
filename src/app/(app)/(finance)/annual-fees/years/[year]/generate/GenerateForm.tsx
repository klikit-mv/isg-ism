'use client';

import { useMemo, useState } from 'react';
import { generateInvoicesAction } from '@/app/actions/annual-fees';
import { SubmitButton } from '@/components/form/ActionForm';
import { Table } from '@/components/Table';
import { PersonType, ScoutSection } from '@/lib/enums';
import type { InvoicePerson } from '@/server/annual-fees';

export function GenerateForm({ year, yearLabel, people }: { year: number; yearLabel: string; people: InvoicePerson[] }) {
  const [picked, setPicked] = useState<string[]>(() => people.filter((p) => !p.invoiced).map((p) => p.key));
  const [sections, setSections] = useState<Record<string, string>>(() => Object.fromEntries(people.filter((p) => p.type === 'Student').map((p) => [p.key, p.section ?? ''])));
  const [search, setSearch] = useState('');
  const [section, setSection] = useState('');
  const visible = useMemo(() => people.filter((p) => (!search || p.name.toLowerCase().includes(search.toLowerCase())) && (!section || p.section === section)), [people, search, section]);
  const toggle = (key: string) => setPicked((c) => (c.includes(key) ? c.filter((k) => k !== key) : [...c, key]));

  return (
    <form action={generateInvoicesAction}>
      <input type="hidden" name="year" value={year} />
      {picked.map((k) => <input key={k} type="hidden" name="people[]" value={k} />)}
      {picked.filter((k) => sections[k]).map((k) => <input key={k} type="hidden" name={`sections[${k}]`} value={sections[k]} />)}
      <div className="card mb-4 grid gap-3 !p-4 sm:grid-cols-4">
        <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} className="input" placeholder="Search name" />
        <select value={section} onChange={(e) => setSection(e.target.value)} className="input">
          <option value="">Any section</option>
          {ScoutSection.values.map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <div className="flex gap-2 sm:col-span-2">
          <button type="button" className="btn-secondary btn-sm" onClick={() => setPicked(people.map((p) => p.key))}>Select all</button>
          <button type="button" className="btn-secondary btn-sm" onClick={() => setPicked([])}>Deselect all</button>
          <SubmitButton className="btn-primary btn-sm ml-auto">Generate</SubmitButton>
        </div>
      </div>
      <Table headers={['', 'Name', 'Type', `Section for ${yearLabel}`, 'Status']}>
        {visible.map((p) => (
          <tr key={p.key}>
            <td><input type="checkbox" checked={picked.includes(p.key)} onChange={() => toggle(p.key)} className="rounded border-gray-300 text-navy-600" aria-label={`Select ${p.name}`} /></td>
            <td data-label="Name" className="font-medium">{p.name} <span className="block text-xs text-gray-400">{p.nationalId}</span></td>
            <td data-label="Type">{PersonType.label(p.type)}</td>
            <td data-label="Section">
              {p.type === 'Student' ? (
                <select value={sections[p.key]} onChange={(e) => setSections({ ...sections, [p.key]: e.target.value })} className="input !w-auto !py-1 text-xs">
                  {ScoutSection.values.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
              ) : '—'}
            </td>
            <td data-label="Status">{p.invoiced ? <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs text-emerald-800">Already invoiced</span> : <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-700">Not invoiced</span>}</td>
          </tr>
        ))}
      </Table>
    </form>
  );
}
