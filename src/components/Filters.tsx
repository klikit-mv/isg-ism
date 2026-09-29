/** A GET form for list filters. Children are inputs; the buttons are added here. */
export function Filters({ children, action }: { children: React.ReactNode; action?: string }) {
  return (
    <form method="GET" action={action} className="card mb-4 flex flex-wrap items-end gap-3 !p-3 sm:!p-4">
      {children}
      <div className="flex gap-2">
        <button type="submit" className="btn-primary btn-sm">Filter</button>
        <a href={action ?? '?'} className="btn-secondary btn-sm">Reset</a>
      </div>
    </form>
  );
}

/** Plain (uncontrolled) inputs for filter forms. */
export function FilterInput({ name, label, value, placeholder, type = 'text' }: { name: string; label: string; value?: string; placeholder?: string; type?: string }) {
  return (
    <div>
      <label htmlFor={`f-${name}`} className="label">{label}</label>
      <input id={`f-${name}`} name={name} type={type} defaultValue={value ?? ''} placeholder={placeholder} className="input" />
    </div>
  );
}

export function FilterSelect({ name, label, value, options, placeholder = 'Any' }: { name: string; label: string; value?: string; options: { value: string; label: string }[]; placeholder?: string }) {
  return (
    <div>
      <label htmlFor={`f-${name}`} className="label">{label}</label>
      <select id={`f-${name}`} name={name} defaultValue={value ?? ''} className="input">
        <option value="">{placeholder}</option>
        {options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
    </div>
  );
}
