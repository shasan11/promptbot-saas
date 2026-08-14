import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, usePage } from '@inertiajs/react';

export default function HorizontalWorkspaceShell({ workspace, title, description, actions, items = [], sections = [], children, flush = false }) {
    const permissions = usePage().props.auth?.permissions || [];
    const source = sections.length ? sections.flatMap((section) => section.items) : items;
    const visible = source.filter((item) => !item.permission || permissions.includes(item.permission) || item.permissions?.some((permission) => permissions.includes(permission)));
    const href = (item) => route(item.route || item.routeName, item.routeParams || undefined);

    return <AuthenticatedLayout title={workspace}>
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <nav className="flex gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50/70 px-2 sm:px-3" aria-label={`${workspace} navigation`}>
                {visible.map((item) => {
                    const Icon = item.icon;
                    const active = route().current(item.pattern || item.active);
                    return <Link key={`${item.route || item.routeName}-${item.label}`} href={href(item)} aria-current={active ? 'page' : undefined} className={`flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-3 text-xs font-semibold transition sm:text-[13px] ${active ? 'border-brand-600 bg-white text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'}`}>{Icon && <Icon className="h-3.5 w-3.5" />}{item.label}</Link>;
                })}
            </nav>
            {!flush && <div className="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5"><div className="min-w-0"><h1 className="text-lg font-bold tracking-tight text-navy-900">{title}</h1>{description && <p className="mt-1 max-w-3xl text-sm text-slate-500">{description}</p>}</div>{actions && <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:shrink-0 [&>*]:w-full sm:[&>*]:w-auto">{actions}</div>}</div>}
            <div className={flush ? 'flex min-h-0 flex-1 flex-col' : 'min-w-0 p-4 sm:p-5'}>{children}</div>
        </div>
    </AuthenticatedLayout>;
}
