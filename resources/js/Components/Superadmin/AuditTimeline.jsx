export default function AuditTimeline({ items = [] }) {
    if (!items.length) {
        return (
            <div className="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                No audit events yet
            </div>
        );
    }

    return (
        <ol className="space-y-4">
            {items.map((item, index) => (
                <li key={item.id || index} className="relative pl-8">
                    <span className="absolute left-0 top-1.5 h-3 w-3 rounded-full bg-slate-950 ring-4 ring-slate-100" />
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="font-semibold text-slate-950">{item.action}</div>
                        <div className="mt-1 text-sm text-slate-500">{item.created_at}</div>
                    </div>
                </li>
            ))}
        </ol>
    );
}
