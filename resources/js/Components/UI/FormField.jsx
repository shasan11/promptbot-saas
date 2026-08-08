export default function FormField({ id, label, hint, error, required = false, optional = false, children, className = '' }) {
    return (
        <div className={className}>
            {label && (
                <label htmlFor={id} className="flex items-baseline justify-between text-sm font-medium text-slate-700">
                    <span>
                        {label}
                        {required && <span className="ml-0.5 text-rose-600">*</span>}
                    </span>
                    {optional && <span className="text-xs font-normal text-slate-400">Optional</span>}
                </label>
            )}
            <div className={label ? 'mt-1.5' : ''}>{children}</div>
            {hint && !error && <p className="mt-1.5 text-xs text-slate-500">{hint}</p>}
            {error && (
                <p className="mt-1.5 text-xs font-medium text-rose-600" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}
