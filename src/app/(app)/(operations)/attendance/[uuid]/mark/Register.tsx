'use client';

import { useMemo, useState, useTransition } from 'react';
import { saveAttendanceAction, updateFeeAction } from '@/app/actions/attendance';
import { Empty } from '@/components/PageHeader';
import { Table } from '@/components/Table';
import { AttendanceStatus, ScoutSection } from '@/lib/enums';
import { formatMoney } from '@/lib/money';
import type { RegisterRow } from '@/server/attendance-queries';

const CHOICES: [string, string][] = [['0', 'Not paid'], ['5', '5'], ['10', '10'], ['15', '15'], ['other', 'Other']];

type Row = { status: string; remarks: string; payment: string; other: string };

export function Register({ uuid, chargeFee, feeDue: initialFee, initial }: { uuid: string; chargeFee: boolean; feeDue: string; initial: RegisterRow[] }) {
  const [rows, setRows] = useState<Record<number, Row>>(() => Object.fromEntries(initial.map((r) => [r.studentId, { status: r.status, remarks: r.remarks, payment: r.payment, other: r.other }])));
  const [search, setSearch] = useState('');
  const [section, setSection] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [feeDue, setFeeDue] = useState(initialFee);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [pending, start] = useTransition();

  const set = (id: number, patch: Partial<Row>) => setRows((prev) => ({ ...prev, [id]: { ...prev[id], ...patch } }));
  const visible = useMemo(() => initial.filter((s) => {
    const row = rows[s.studentId];
    if (search && !`${s.name} ${s.indexNumber}`.toLowerCase().includes(search.toLowerCase())) return false;
    if (section && s.section !== section) return false;
    if (statusFilter === 'unmarked') return row.status === '';
    return !statusFilter || row.status === statusFilter;
  }), [initial, rows, search, section, statusFilter]);

  const save = () => start(async () => {
    const result = await saveAttendanceAction(uuid, Object.fromEntries(Object.entries(rows).map(([id, r]) => [id, r])));
    setMessage({ ok: result.ok, text: result.message });
  });
  const updateFee = () => start(async () => {
    const result = await updateFeeAction(uuid, feeDue);
    setMessage({ ok: result.ok, text: result.message });
  });

  if (initial.length === 0) return <Empty message="Nobody on this roster is in your groups." />;

  return (
    <div className="space-y-4">
      {message && (
        <div className={`rounded-lg border px-4 py-3 text-sm ${message.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200' : 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200'}`} role={message.ok ? 'status' : 'alert'}>{message.text}</div>
      )}

      {chargeFee && (
        <div className="card flex flex-wrap items-end gap-3 !p-4">
          <div>
            <label className="label" htmlFor="feeDue">Fee due per scout</label>
            <input id="feeDue" type="number" step="0.01" min="0" value={feeDue} onChange={(e) => setFeeDue(e.target.value)} className="input w-40" />
          </div>
          <button type="button" onClick={updateFee} disabled={pending} className="btn-secondary">Update fee</button>
          <p className="text-xs text-gray-500">Changing the fee re-prices every class fee for this activity.</p>
        </div>
      )}

      <div className="card grid gap-3 !p-4 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} className="input" placeholder="Search name" />
        <select value={section} onChange={(e) => setSection(e.target.value)} className="input">
          <option value="">Any section</option>
          {ScoutSection.values.map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="input">
          <option value="">Any status</option>
          <option value="unmarked">Unmarked</option>
          {AttendanceStatus.values.map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <div className="flex gap-2">
          <button type="button" className="btn-secondary btn-sm" onClick={() => setRows((p) => Object.fromEntries(Object.entries(p).map(([id, r]) => [id, { ...r, status: 'Present' }])))}>Mark all present</button>
          <button type="button" className="btn-secondary btn-sm" onClick={() => setRows((p) => Object.fromEntries(Object.keys(p).map((id) => [id, { status: '', remarks: '', payment: '', other: '' }])))}>Clear</button>
        </div>
      </div>

      <p className="text-sm text-gray-500">{visible.length} of {initial.length} scouts shown. Rows left blank are not saved.</p>

      <Table headers={['Scout', 'Status', 'Remarks', ...(chargeFee ? ['Paid now'] : [])]}>
        {visible.map((s) => {
          const row = rows[s.studentId];
          return (
            <tr key={s.studentId}>
              <td data-label="Scout"><div className="font-medium">{s.name}</div><div className="text-xs text-gray-500">{s.section} · {s.indexNumber}</div></td>
              <td data-label="Status">
                <div className="flex flex-wrap justify-end gap-1 md:justify-start">
                  {AttendanceStatus.values.map((status) => (
                    <label key={status} className="cursor-pointer">
                      <input type="radio" className="peer sr-only" name={`status-${s.studentId}`} value={status} checked={row.status === status} onChange={() => set(s.studentId, { status })} />
                      <span className="inline-block rounded-md border border-gray-300 px-2 py-1 text-xs peer-checked:border-navy-600 peer-checked:bg-navy-600 peer-checked:text-white dark:border-gray-600">{status}</span>
                    </label>
                  ))}
                </div>
              </td>
              <td data-label="Remarks"><input type="text" maxLength={255} value={row.remarks} onChange={(e) => set(s.studentId, { remarks: e.target.value })} className="input !py-1 text-xs" /></td>
              {chargeFee && (
                <td data-label="Paid now">
                  {row.status === 'Excused' ? <span className="text-xs text-gray-400">Excused — no fee</span> : (
                    <>
                      <div className="flex items-center justify-end gap-2 md:justify-start">
                        <select value={row.payment} onChange={(e) => set(s.studentId, { payment: e.target.value })} className="input !w-28 !py-1 text-xs">
                          <option value="">—</option>
                          {CHOICES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        {row.payment === 'other' && <input type="number" step="0.01" min="0" value={row.other} onChange={(e) => set(s.studentId, { other: e.target.value })} className="input !w-24 !py-1 text-xs" placeholder="Amount" />}
                      </div>
                      {s.fee && <div className="mt-1 text-xs text-gray-500">Fee {formatMoney(s.fee.amount)} · paid {formatMoney(s.fee.paid)}</div>}
                    </>
                  )}
                </td>
              )}
            </tr>
          );
        })}
      </Table>

      <div className="sticky bottom-0 flex justify-end border-t border-gray-200 bg-gray-50/90 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
        <button type="button" onClick={save} disabled={pending} className="btn-primary">{pending ? 'Saving…' : 'Save register'}</button>
      </div>
    </div>
  );
}
