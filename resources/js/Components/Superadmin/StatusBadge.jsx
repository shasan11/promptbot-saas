const colors = {
    active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    trial: 'border-blue-200 bg-blue-50 text-blue-700',
    trialing: 'border-blue-200 bg-blue-50 text-blue-700',
    pending: 'border-amber-200 bg-amber-50 text-amber-700',
    provisioning: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    suspended: 'border-rose-200 bg-rose-50 text-rose-700',
    failed: 'border-rose-200 bg-rose-50 text-rose-700',
    cancelled: 'border-slate-200 bg-slate-100 text-slate-600',
    expired: 'border-slate-200 bg-slate-100 text-slate-600',
    live: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    ready: 'border-blue-200 bg-blue-50 text-blue-700',
    configured: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    showing: 'border-slate-200 bg-slate-100 text-slate-600',
    successful: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-700',
    info: 'border-blue-200 bg-blue-50 text-blue-700',
    masked: 'border-purple-200 bg-purple-50 text-purple-700',
};

export default function StatusBadge({ status }) {
    const value = String(status?.value || status || 'unknown').toLowerCase();

    return (
        <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize ${colors[value] || 'border-slate-200 bg-slate-100 text-slate-600'}`}>
            {String(value).replaceAll('_', ' ')}
        </span>
    );
}
