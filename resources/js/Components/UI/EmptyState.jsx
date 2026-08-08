export default function EmptyState({ icon: Icon, title, description, action, className = '' }) {
    return (
        <div className={`flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50/50 px-6 py-14 text-center ${className}`}>
            {Icon && (
                <span className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-white text-slate-400 shadow-soft">
                    <Icon className="h-6 w-6" aria-hidden="true" />
                </span>
            )}
            <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            {description && <p className="mt-1 max-w-sm text-sm text-slate-500">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
