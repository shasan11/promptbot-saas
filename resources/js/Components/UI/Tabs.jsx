export default function Tabs({ items = [], active, onChange }) {
    return (
        <div role="tablist" className="flex gap-1 overflow-x-auto border-b border-slate-200">
            {items.map((item) => {
                const selected = item.value === active;
                return (
                    <button
                        key={item.value}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        onClick={() => onChange(item.value)}
                        className={`whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-800 ${
                            selected ? 'border-brand-600 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        {item.label}
                        {typeof item.count === 'number' && (
                            <span className={`ml-1.5 rounded-full px-1.5 py-0.5 text-xs ${selected ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500'}`}>
                                {item.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
