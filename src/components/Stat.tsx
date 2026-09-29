export function Stat({ label, value, tone = 'navy' }: { label: string; value: React.ReactNode; tone?: 'navy' | 'gold' }) {
  return (
    <div className="card !p-4">
      <div className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</div>
      <div className={`mt-1 text-xl font-bold ${tone === 'gold' ? 'text-gold-600 dark:text-gold-400' : 'text-navy-800 dark:text-navy-200'}`}>{value}</div>
    </div>
  );
}
