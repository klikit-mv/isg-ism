import type { Tone } from '@/lib/enums';
import type { Enum } from '@/lib/enums';

export const toneClasses: Record<Tone, string> = {
  green: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  red: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
  blue: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  purple: 'bg-navy-100 text-navy-800 dark:bg-navy-900/60 dark:text-navy-200',
  gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
};

/** A status pill. Pass an enum and stored value, or plain text with a tone. */
export function Badge(props: { value?: string | null; of?: Enum<any>; tone?: Tone; children?: React.ReactNode; className?: string }) {
  const { value, of, tone, children, className = '' } = props;
  const resolvedTone: Tone = tone ?? (of ? of.tone(value) : 'gray');
  const text = children ?? (of ? of.label(value) : value);
  return (
    <span className={`inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ${toneClasses[resolvedTone]} ${className}`}>
      {text}
    </span>
  );
}
