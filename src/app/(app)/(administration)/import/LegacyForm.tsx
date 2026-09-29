'use client';

import { useActionState, useTransition } from 'react';
import { legacyImportAction, type LegacyState } from '@/app/actions/legacy-import';
import { Badge } from '@/components/Badge';

function Buttons({ pending }: { pending: boolean }) {
  return (
    <div className="flex flex-wrap gap-2">
      <button type="submit" name="mode" value="inspect" className="btn-secondary" disabled={pending}>{pending ? 'Working…' : 'Inspect'}</button>
      <button type="submit" name="mode" value="dry" className="btn-accent" disabled={pending}>Dry run</button>
      <button type="submit" name="mode" value="import" className="btn-primary" disabled={pending}>Import for real</button>
    </div>
  );
}

export function LegacyForm({ sheets }: { sheets: string[] }) {
  const [state, action] = useActionState<LegacyState | null, FormData>(legacyImportAction, null);
  const [pending, startTransition] = useTransition();
  // Submitted by hand so the chosen file stays in the form for the next button.
  const submit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const data = new FormData(e.currentTarget, (e.nativeEvent as SubmitEvent).submitter);
    startTransition(() => action(data));
  };
  return (
    <div className="space-y-4">
      <form onSubmit={submit} className="space-y-3">
        <div className="grid gap-3 sm:grid-cols-2">
          <div><label className="label" htmlFor="file">Workbook (.xlsx)</label><input id="file" name="file" type="file" accept=".xlsx" className="input" required /></div>
          <div>
            <label className="label" htmlFor="sheet">Only this sheet (optional)</label>
            <select id="sheet" name="sheet" className="input" defaultValue=""><option value="">All sheets</option>{sheets.map((s) => <option key={s} value={s}>{s}</option>)}</select>
          </div>
        </div>
        <Buttons pending={pending} />
      </form>
      {state?.error && <p role="alert" className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200">{state.error}</p>}
      {state?.inspect && (
        <div className="space-y-2">
          {state.inspect.missingIdentity.length > 0 && <p className="text-sm text-amber-700">No {state.inspect.missingIdentity.join(' or ')} sheet: people the other sheets refer to must already exist.</p>}
          <table className="w-full text-sm">
            <thead className="text-left text-xs uppercase text-gray-500"><tr><th className="py-1">Sheet</th><th>Rows</th><th>Valid</th><th>Invalid</th><th>Notes</th></tr></thead>
            <tbody>
              {state.inspect.sheets.map((s) => (
                <tr key={s.name} className="border-t border-gray-100 dark:border-gray-700">
                  <td className="py-1 font-medium">{s.name}</td><td>{s.rows}</td><td>{s.valid}</td><td>{s.invalid}</td>
                  <td>{!s.known ? <Badge tone="gray">Ignored</Badge> : s.missing.length ? <Badge tone="red">Missing {s.missing.join(', ')}</Badge> : <Badge tone="green">OK</Badge>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {state?.report && (
        <div className="space-y-3">
          <p className="text-sm font-medium">{state.report.dryRun ? 'Dry run: nothing was saved.' : 'Imported.'}</p>
          <table className="w-full text-sm">
            <thead className="text-left text-xs uppercase text-gray-500"><tr><th className="py-1">Sheet</th><th>Imported</th><th>Problems</th></tr></thead>
            <tbody>{Object.entries(state.report.counts).map(([name, c]) => <tr key={name} className="border-t border-gray-100 dark:border-gray-700"><td className="py-1 font-medium">{name}</td><td>{c.imported}</td><td>{c.errors}</td></tr>)}</tbody>
          </table>
          {state.report.errors.length > 0 && (
            <div className="max-h-96 overflow-auto rounded-lg border border-gray-200 dark:border-gray-700">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th className="px-3 py-2">Sheet</th><th className="px-3 py-2">Row</th><th className="px-3 py-2">Message</th></tr></thead>
                <tbody>{state.report.errors.slice(0, 500).map((e, i) => <tr key={i} className="border-t border-gray-100 dark:border-gray-700"><td className="px-3 py-1">{e.sheet}</td><td className="px-3 py-1">{e.row}</td><td className="px-3 py-1">{e.message}</td></tr>)}</tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
