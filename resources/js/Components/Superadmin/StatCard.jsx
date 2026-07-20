export default function StatCard({ title, value, prefix, suffix, tone = 'slate' }) {
    const tones = {
        slate: 'bg-slate-950 text-white',
        blue: 'bg-blue-600 text-white',
        emerald: 'bg-emerald-600 text-white',
        amber: 'bg-amber-500 text-white',
        rose: 'bg-rose-600 text-white',
    };

    return (
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div className={`mb-5 h-1.5 w-12 rounded-full ${tones[tone] || tones.slate}`} />
            <div className="text-sm font-medium text-slate-500">{title}</div>
            <div className="mt-2 flex items-baseline gap-1 text-3xl font-bold tracking-tight text-slate-950">
                {prefix && <span className="text-xl text-slate-400">{prefix}</span>}
                <span>{value}</span>
                {suffix && <span className="text-sm font-semibold text-slate-400">{suffix}</span>}
            </div>
        </div>
    );
}
