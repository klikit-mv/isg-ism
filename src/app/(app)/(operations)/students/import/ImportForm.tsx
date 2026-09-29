'use client';

import { useActionState } from 'react';
import { useFormStatus } from 'react-dom';
import { importStudentsAction, type ImportState } from '@/app/actions/student-import';
import { Badge } from '@/components/Badge';

const tones = { ready: 'blue', created: 'green', exists: 'amber', error: 'red' } as const;

function Buttons() {
  const { pending } = useFormStatus();
  return (
    <div className="flex flex-wrap gap-2">
      <button type="submit" name="mode" value="check" className="btn-secondary" disabled={pending}>{pending ? 'Working…' : 'Check file'}</button>
      <button type="submit" name="mode" value="import" className="btn-primary" disabled={pending}>Import scouts</button>
    </div>
  );
}

export function ImportForm() {
  const [state, action] = useActionState<ImportState | null, FormData>(importStudentsAction, null);
  const report = state?.report;
  return (
    <div className="space-y-4">
      <form action={action} className="space-y-3">
        <div>
          <label className="label" htmlFor="file">Excel or CSV file</label>
          <input id="file" name="file" type="file" accept=".xlsx,.csv" className="input" required />
        </div>
        <Buttons />
      </form>
      {state?.error && <p role="alert" className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200">{state.error}</p>}
      {report && report.missing.length > 0 && <p role="alert" className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">The file is missing these columns: {report.missing.join(', ')}.</p>}
      {report && report.missing.length === 0 && (
        <div className="space-y-3">
          <p className="text-sm font-medium">
            {state?.mode === 'import' ? `${report.created} enrolled, ` : `${report.lines.filter((l) => l.result === 'ready').length} ready, `}
            {report.skipped} already exist, {report.errors} with problems.
          </p>
          <div className="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th className="px-3 py-2">Row</th><th className="px-3 py-2">Name</th><th className="px-3 py-2">National ID</th><th className="px-3 py-2">Result</th><th className="px-3 py-2">Message</th></tr></thead>
              <tbody>
                {report.lines.map((l) => (
                  <tr key={l.row} className="border-t border-gray-100 dark:border-gray-700">
                    <td className="px-3 py-2">{l.row}</td><td className="px-3 py-2">{l.name}</td><td className="px-3 py-2">{l.national_id}</td>
                    <td className="px-3 py-2"><Badge tone={tones[l.result]}>{l.result}</Badge></td><td className="px-3 py-2">{l.message}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
