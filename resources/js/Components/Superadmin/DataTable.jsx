function readValue(record, key) {
    if (Array.isArray(key)) {
        return key.reduce((value, segment) => value?.[segment], record);
    }

    return record?.[key];
}

export default function DataTable({ columns = [], dataSource = [], rowKey = 'id', emptyText = 'No records found.' }) {
    return (
        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            {columns.map((column) => (
                                <th key={column.key || column.dataIndex || column.title} className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                    {column.title}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 bg-white">
                        {dataSource.length ? dataSource.map((record, index) => (
                            <tr key={typeof rowKey === 'function' ? rowKey(record) : record[rowKey] || index} className="hover:bg-slate-50">
                                {columns.map((column) => {
                                    const value = readValue(record, column.dataIndex);
                                    return (
                                        <td key={column.key || column.dataIndex || column.title} className="px-4 py-4 align-top text-slate-700">
                                            {column.render ? column.render(value, record, index) : value}
                                        </td>
                                    );
                                })}
                            </tr>
                        )) : (
                            <tr>
                                <td className="px-4 py-10 text-center text-slate-500" colSpan={columns.length}>
                                    {emptyText}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
