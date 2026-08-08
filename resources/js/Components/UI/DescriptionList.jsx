const columnClasses = {
    1: 'sm:grid-cols-1',
    2: 'sm:grid-cols-2',
    3: 'sm:grid-cols-3',
    4: 'sm:grid-cols-4',
};

export default function DescriptionList({ items = [], columns = 2 }) {
    return (
        <dl className={`grid grid-cols-1 gap-x-6 gap-y-4 ${columnClasses[columns] || columnClasses[2]}`}>
            {items.map((item) => (
                <div key={item.label} className="min-w-0">
                    <dt className="text-xs font-medium uppercase tracking-wide text-slate-400">{item.label}</dt>
                    <dd className="mt-1 truncate text-sm text-slate-800" title={typeof item.value === 'string' ? item.value : undefined}>
                        {item.value ?? <span className="text-slate-400">—</span>}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
