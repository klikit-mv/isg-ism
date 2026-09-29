'use client';

import { useFormState } from './ActionForm';

const errorOf = (state: ReturnType<typeof useFormState>, name: string) => state?.fields?.[name] ?? state?.fields?.[name.replace(/\[(\w+)\]/g, '.$1')];
const valueOf = (state: ReturnType<typeof useFormState>, name: string, fallback: unknown) => {
  const typed = state?.values?.[name];
  return typed !== undefined && typed !== null ? String(typed) : fallback === null || fallback === undefined ? '' : String(fallback);
};

type Common = { name: string; label?: string; help?: string; id?: string };

export function Input({ name, label, help, id, defaultValue, className = 'input', ...rest }: Common & { defaultValue?: string | number | null } & Omit<React.InputHTMLAttributes<HTMLInputElement>, 'name' | 'id' | 'defaultValue'>) {
  const state = useFormState();
  const error = errorOf(state, name);
  const isSecret = rest.type === 'password';
  const inputId = id ?? name.replace(/[\[\]]/g, '_');
  return (
    <div>
      {label ? <label htmlFor={inputId} className="label">{label}</label> : null}
      <input
        id={inputId}
        name={name}
        defaultValue={isSecret || rest.type === 'file' ? undefined : valueOf(state, name, defaultValue)}
        key={state ? JSON.stringify([state.values?.[name], state.error]) : 'initial'}
        className={className}
        aria-invalid={error ? true : undefined}
        {...rest}
      />
      {help ? <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{help}</p> : null}
      {error ? <p className="mt-1 text-xs text-rose-600 dark:text-rose-400">{error}</p> : null}
    </div>
  );
}

export function Textarea({ name, label, help, id, defaultValue, rows = 3, className = 'input', ...rest }: Common & { defaultValue?: string | null } & Omit<React.TextareaHTMLAttributes<HTMLTextAreaElement>, 'name' | 'id' | 'defaultValue'>) {
  const state = useFormState();
  const error = errorOf(state, name);
  const inputId = id ?? name;
  return (
    <div>
      {label ? <label htmlFor={inputId} className="label">{label}</label> : null}
      <textarea id={inputId} name={name} rows={rows} defaultValue={valueOf(state, name, defaultValue)} key={state ? JSON.stringify([state.values?.[name], state.error]) : 'initial'} className={className} {...rest} />
      {help ? <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{help}</p> : null}
      {error ? <p className="mt-1 text-xs text-rose-600 dark:text-rose-400">{error}</p> : null}
    </div>
  );
}

export interface Option {
  value: string;
  label: string;
}

export function Select({ name, label, help, id, options, defaultValue, placeholder, className = 'input', ...rest }: Common & { options: Option[]; defaultValue?: string | null; placeholder?: string } & Omit<React.SelectHTMLAttributes<HTMLSelectElement>, 'name' | 'id' | 'defaultValue'>) {
  const state = useFormState();
  const error = errorOf(state, name);
  const inputId = id ?? name.replace(/[\[\]]/g, '_');
  return (
    <div>
      {label ? <label htmlFor={inputId} className="label">{label}</label> : null}
      <select id={inputId} name={name} defaultValue={valueOf(state, name, defaultValue)} key={state ? JSON.stringify([state.values?.[name], state.error]) : 'initial'} className={className} {...rest}>
        {placeholder !== undefined ? <option value="">{placeholder}</option> : null}
        {options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
      {help ? <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{help}</p> : null}
      {error ? <p className="mt-1 text-xs text-rose-600 dark:text-rose-400">{error}</p> : null}
    </div>
  );
}

/** A checkbox that always submits a value ("0" when unticked), like the old hidden-input pattern. */
export function Checkbox({ name, label, defaultChecked, value = '1', ...rest }: { name: string; label: React.ReactNode; defaultChecked?: boolean; value?: string } & Omit<React.InputHTMLAttributes<HTMLInputElement>, 'name' | 'type' | 'value' | 'defaultChecked'>) {
  const state = useFormState();
  const typed = state?.values?.[name];
  const checked = typed !== undefined ? typed === value || typed === true : !!defaultChecked;
  return (
    <label className="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
      <input type="hidden" name={name} value="0" />
      <input type="checkbox" name={name} value={value} defaultChecked={checked} key={state ? JSON.stringify(state.values?.[name]) : 'initial'} className="rounded border-gray-300 text-navy-600 focus:ring-navy-500 dark:border-gray-600 dark:bg-gray-900" {...rest} />
      <span>{label}</span>
    </label>
  );
}

/** A group of checkboxes sharing one name (submitted as name[]). */
export function CheckboxGroup({ name, options, defaultValues = [], legend, help }: { name: string; options: Option[]; defaultValues?: string[]; legend?: string; help?: string }) {
  const state = useFormState();
  const typed = state?.values?.[name];
  const chosen = Array.isArray(typed) ? (typed as string[]) : defaultValues;
  return (
    <fieldset>
      {legend ? <legend className="label">{legend}</legend> : null}
      <div className="flex flex-wrap gap-4">
        {options.map((o) => (
          <label key={o.value} className="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name={`${name}[]`} value={o.value} defaultChecked={chosen.includes(o.value)} key={JSON.stringify(chosen)} className="rounded border-gray-300 text-navy-600 focus:ring-navy-500 dark:border-gray-600 dark:bg-gray-900" />
            <span>{o.label}</span>
          </label>
        ))}
      </div>
      {help ? <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{help}</p> : null}
    </fieldset>
  );
}

/** A file input styled like the old drop zone. */
export function FileInput({ name, label, accept, help }: { name: string; label?: string; accept?: string; help?: string }) {
  const state = useFormState();
  const error = errorOf(state, name);
  return (
    <div>
      {label ? <label htmlFor={name} className="label">{label}</label> : null}
      <input id={name} name={name} type="file" accept={accept} className="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-50 file:px-3 file:py-2 file:text-navy-700 dark:text-gray-300 dark:file:bg-navy-900 dark:file:text-navy-200" />
      {help ? <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{help}</p> : null}
      {error ? <p className="mt-1 text-xs text-rose-600 dark:text-rose-400">{error}</p> : null}
    </div>
  );
}
