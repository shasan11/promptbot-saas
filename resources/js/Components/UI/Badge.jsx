const tones = {
    neutral: 'border-slate-200 bg-slate-100 text-slate-600',
    brand: 'border-brand-200 bg-brand-50 text-brand-700',
    info: 'border-blue-200 bg-blue-50 text-blue-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-700',
    danger: 'border-rose-200 bg-rose-50 text-rose-700',
};

export default function Badge({ tone = 'neutral', children, className = '' }) {
    return (
        <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold ${tones[tone] || tones.neutral} ${className}`}>
            {children}
        </span>
    );
}
