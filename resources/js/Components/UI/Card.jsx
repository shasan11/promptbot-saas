export function Card({ className = '', children, padded = true, ...props }) {
    return (
        <div className={`rounded-lg border border-slate-200 bg-white shadow-soft ${padded ? 'p-5' : ''} ${className}`} {...props}>
            {children}
        </div>
    );
}

export function SectionCard({ title, description, actions, children, className = '' }) {
    return (
        <section className={`rounded-lg border border-slate-200 bg-white shadow-soft ${className}`}>
            {(title || actions) && (
                <div className="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        {title && <h2 className="text-sm font-semibold text-slate-900">{title}</h2>}
                        {description && <p className="mt-0.5 text-xs text-slate-500">{description}</p>}
                    </div>
                    {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
                </div>
            )}
            <div className="p-5">{children}</div>
        </section>
    );
}

export default Card;
