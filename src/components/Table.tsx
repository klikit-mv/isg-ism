/** Responsive table: a table on desktop, stacked cards on phones (see .rtable in globals.css). */
export function Table({ headers, children }: { headers: string[]; children: React.ReactNode }) {
  return (
    <div className="md:overflow-hidden md:rounded-xl md:border md:border-gray-200 md:bg-white md:shadow-sm md:dark:border-gray-700 md:dark:bg-gray-800">
      <div className="md:overflow-x-auto">
        <table className="rtable">
          <thead>
            <tr>{headers.map((h, i) => <th key={i} scope="col">{h}</th>)}</tr>
          </thead>
          <tbody>{children}</tbody>
        </table>
      </div>
    </div>
  );
}
