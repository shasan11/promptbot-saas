const tones = {
    slate: { chip: 'bg-navy-50 text-navy-700', accent: 'from-navy-700 to-navy-900' },
    blue: { chip: 'bg-blue-50 text-blue-700', accent: 'from-blue-500 to-blue-700' },
    emerald: { chip: 'bg-brand-50 text-brand-700', accent: 'from-brand-500 to-brand-700' },
    amber: { chip: 'bg-amber-50 text-amber-700', accent: 'from-amber-400 to-amber-600' },
    rose: { chip: 'bg-rose-50 text-rose-700', accent: 'from-rose-500 to-rose-700' },
};

export default function StatCard({ title, value, prefix, suffix, tone = 'slate', icon: Icon }) {
    const { chip, accent } = tones[tone] || tones.slate;

    return (
        <div className="group relative overflow-hidden rounded-lg border border-slate-200 bg-white p-5 shadow-soft transition duration-150 hover:-translate-y-0.5 hover:shadow-soft-lg motion-reduce:transform-none">
            <div className={`absolute inset-x-0 top-0 h-1 bg-gradient-to-r ${accent}`} aria-hidden="true" />
            <div className="flex items-start justify-between gap-3">
                <div className="text-sm font-medium text-slate-500">{title}</div>
                {Icon && (
                    <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-md ${chip}`}>
                        <Icon className="h-[18px] w-[18px]" strokeWidth={1.8} aria-hidden="true" />
                    </span>
                )}
            </div>
            <div className="mt-3 flex items-baseline gap-1 text-3xl font-bold tracking-tight text-slate-900">
                {prefix && <span className="text-xl text-slate-400">{prefix}</span>}
                <span>{value}</span>
                {suffix && <span className="text-sm font-semibold text-slate-400">{suffix}</span>}
            </div>
        </div>
    );
}
