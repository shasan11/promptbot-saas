export default function Panel({ title, description, actions, children, className = '' }) {
    return <section className={`rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}>
        {(title || actions) && <header className="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4"><div><h2 className="font-semibold text-slate-900">{title}</h2>{description && <p className="mt-1 text-sm text-slate-500">{description}</p>}</div>{actions}</header>}
        <div className="p-5">{children}</div>
    </section>;
}
