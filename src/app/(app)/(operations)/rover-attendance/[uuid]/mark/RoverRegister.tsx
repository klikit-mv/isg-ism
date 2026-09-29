'use client';

import { useState, useTransition } from 'react';
import { saveRoverAction } from '@/app/actions/attendance';
import { Empty } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { RoverAttendanceStatus } from '@/lib/enums';

interface Person { id: number; name: string }

export function RoverRegister({ uuid, required, optional, available, initial }: { uuid: string; required: Person[]; optional: Person[]; available: Person[]; initial: Record<number, string> }) {
  const [marks, setMarks] = useState<Record<number, string>>(initial);
  const [added, setAdded] = useState<number[]>([]);
  const [search, setSearch] = useState('');
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [pending, start] = useTransition();

  const addedRovers = available.filter((r) => added.includes(r.id));
  const remaining = available.filter((r) => !added.includes(r.id) && (!search || r.name.toLowerCase().includes(search.toLowerCase())));
  const save = () => start(async () => {
    const result = await saveRoverAction(uuid, Object.fromEntries(Object.entries(marks).map(([k, v]) => [k, v])));
    setMessage({ ok: result.ok, text: result.message });
  });
  const optionalRow = (r: Person) => (
    <label key={r.id} className="mb-1 flex items-center gap-2 text-sm">
      <input type="checkbox" checked={marks[r.id] === 'Present'} onChange={(e) => setMarks({ ...marks, [r.id]: e.target.checked ? 'Present' : '' })} className="rounded border-gray-300 text-navy-600" /> {r.name}
    </label>
  );

  return (
    <div className="space-y-6">
      {message && <div className={`rounded-lg border px-4 py-3 text-sm ${message.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`} role={message.ok ? 'status' : 'alert'}>{message.text}</div>}

      <section>
        <h2 className="mb-2 font-semibold">Required Rovers <span className="text-sm font-normal text-gray-500">(on the roster)</span></h2>
        {required.length === 0 ? <Empty message="No Rovers are on this roster." /> : (
          <Table headers={['Rover', 'Status']}>
            {required.map((r) => (
              <tr key={r.id}>
                <td data-label="Rover" className="font-medium">{r.name}</td>
                <td data-label="Status">
                  <div className="flex flex-wrap justify-end gap-1 md:justify-start">
                    {RoverAttendanceStatus.values.map((s) => (
                      <label key={s} className="cursor-pointer">
                        <input type="radio" className="peer sr-only" name={`rover-${r.id}`} checked={marks[r.id] === s} onChange={() => setMarks({ ...marks, [r.id]: s })} />
                        <span className="inline-block rounded-md border border-gray-300 px-2 py-1 text-xs peer-checked:border-navy-600 peer-checked:bg-navy-600 peer-checked:text-white dark:border-gray-600">{s}</span>
                      </label>
                    ))}
                  </div>
                </td>
              </tr>
            ))}
          </Table>
        )}
      </section>

      <section>
        <h2 className="mb-2 font-semibold">Optional Rovers <span className="text-sm font-normal text-gray-500">(assistant leaders of targeted groups — Present only)</span></h2>
        {optional.length + addedRovers.length === 0 ? <p className="text-sm text-gray-500">No optional Rovers.</p> : [...optional, ...addedRovers].map(optionalRow)}
      </section>

      <section className="card !p-4">
        <h2 className="mb-2 font-semibold">Other Rovers who attended</h2>
        <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} className="input" placeholder="Search Rovers to add" />
        <div className="mt-2 space-y-1">
          {remaining.map((r) => (
            <div key={r.id} className="flex items-center justify-between text-sm">
              <span>{r.name}</span>
              <button type="button" className="btn-secondary btn-sm" onClick={() => { setAdded([...added, r.id]); setMarks({ ...marks, [r.id]: 'Present' }); setSearch(''); }}>Add as present</button>
            </div>
          ))}
        </div>
      </section>

      <div className="flex justify-end"><button type="button" onClick={save} disabled={pending} className="btn-primary">{pending ? 'Saving…' : 'Save Rover register'}</button></div>
    </div>
  );
}
