'use client';

import { useState } from 'react';
import { promoteAction } from '@/app/actions/students';
import { SubmitButton } from '@/components/form/ActionForm';
import { Table } from '@/components/Table';

interface Row {
  id: number;
  name: string;
  indexNumber: string;
  patrol: string | null;
  status: string;
}

export function PromoteForm({ from, to, rows, statusBadges }: { from: string; to: string; rows: Row[]; statusBadges: Record<number, string> }) {
  const [picked, setPicked] = useState<number[]>([]);
  const all = picked.length === rows.length;
  return (
    <form action={promoteAction}>
      <input type="hidden" name="from" value={from} />
      <input type="hidden" name="to" value={to} />
      {picked.map((id) => <input key={id} type="hidden" name="students[]" value={id} />)}
      <div className="mb-3 flex flex-wrap items-center gap-3">
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" className="rounded border-gray-300 text-navy-600" checked={all} onChange={() => setPicked(all ? [] : rows.map((r) => r.id))} />
          Select all ({rows.length})
        </label>
        <SubmitButton className="btn-primary btn-sm">Promote selected to {to}</SubmitButton>
      </div>
      <Table headers={['', 'Scout', 'Index', 'Patrol', 'Status']}>
        {rows.map((s) => (
          <tr key={s.id}>
            <td><input type="checkbox" className="rounded border-gray-300 text-navy-600" aria-label={`Select ${s.name}`} checked={picked.includes(s.id)} onChange={() => setPicked((p) => (p.includes(s.id) ? p.filter((x) => x !== s.id) : [...p, s.id]))} /></td>
            <td data-label="Scout" className="font-medium">{s.name}</td>
            <td data-label="Index">{s.indexNumber}</td>
            <td data-label="Patrol">{s.patrol || '—'}</td>
            <td data-label="Status">{statusBadges[s.id]}</td>
          </tr>
        ))}
      </Table>
    </form>
  );
}
