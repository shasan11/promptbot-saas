import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity, AlertTriangle, AppWindow, Braces, Cable, ClipboardList, Database,
    Globe2, KeyRound, PlugZap, RotateCcw, Settings, ServerCog, ShieldCheck,
} from 'lucide-react';

const SECTIONS = [
    {
        label: 'Connections',
        items: [
            { label: 'Overview', route: 'tenant.admin.connections.overview', pattern: 'tenant.admin.connections.overview', icon: Cable, permission: 'connections.view' },
            { label: 'All connections', route: 'tenant.admin.connections.index', pattern: 'tenant.admin.connections.index', icon: PlugZap, permission: 'connections.view' },
            { label: 'App catalog', route: 'tenant.admin.connections.apps.index', pattern: 'tenant.admin.connections.apps.*', icon: AppWindow, permission: 'connections.catalog.view' },
            { label: 'Data sources', route: 'tenant.admin.connections.data-sources.index', pattern: 'tenant.admin.connections.data-sources.*', icon: ClipboardList, permission: 'connections.data_sources.view' },
        ],
    },
    {
        label: 'Technical sources',
        items: [
            { label: 'API connections', route: 'tenant.admin.connections.api.index', pattern: 'tenant.admin.connections.api.*', icon: Braces, permission: 'connections.api.view' },
            { label: 'Databases', route: 'tenant.admin.connections.databases.index', pattern: 'tenant.admin.connections.databases.*', icon: Database, permission: 'connections.databases.view' },
            { label: 'Webhooks', route: 'tenant.admin.connections.webhooks.index', pattern: 'tenant.admin.connections.webhooks.*', icon: Globe2, permission: 'connections.webhooks.view' },
            { label: 'MCP servers', route: 'tenant.admin.connections.mcp.index', pattern: 'tenant.admin.connections.mcp.*', icon: ServerCog, permission: 'connections.mcp.view' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { label: 'Sync jobs', route: 'tenant.admin.connections.sync-jobs.index', pattern: 'tenant.admin.connections.sync-jobs.*', icon: RotateCcw, permission: 'connections.sync.view' },
            { label: 'Connection logs', route: 'tenant.admin.connections.logs.index', pattern: 'tenant.admin.connections.logs.*', icon: Activity, permission: 'connections.logs.view' },
            { label: 'Failed connections', route: 'tenant.admin.connections.failed.index', pattern: 'tenant.admin.connections.failed.*', icon: AlertTriangle, permission: 'connections.view' },
            { label: 'Credentials', route: 'tenant.admin.connections.credentials.index', pattern: 'tenant.admin.connections.credentials.*', icon: KeyRound, permission: 'connections.credentials.view' },
            { label: 'Permissions', route: 'tenant.admin.connections.permissions.index', pattern: 'tenant.admin.connections.permissions.*', icon: ShieldCheck, permission: 'connections.permissions.manage' },
            { label: 'Settings', route: 'tenant.admin.connections.settings.index', pattern: 'tenant.admin.connections.settings.*', icon: Settings, permission: 'connections.settings.manage' },
        ],
    },
];

export default function ConnectionsShell({ title, description, actions, children }) {
    const { auth } = usePage().props;
    const can = (permission) => !permission || auth?.permissions?.includes(permission);
    const sections = SECTIONS.map((section) => ({ ...section, items: section.items.filter((item) => can(item.permission)) })).filter((section) => section.items.length);
    const flatItems = sections.flatMap((section) => section.items);
    const itemHref = (item) => (item ? route(item.route) : '');

    return (
        <AuthenticatedLayout title="Connections">
            <div className="grid gap-6 xl:grid-cols-[236px_1fr]">
                <div className="xl:hidden">
                    <Select value={itemHref(flatItems.find((item) => route().current(item.pattern)) || flatItems[0])} onChange={(event) => event.target.value && window.location.assign(event.target.value)} disabled={!flatItems.length}>
                        {sections.map((section) => (
                            <optgroup key={section.label} label={section.label}>
                                {section.items.map((item) => <option key={item.route} value={itemHref(item)}>{item.label}</option>)}
                            </optgroup>
                        ))}
                    </Select>
                </div>

                <nav className="hidden space-y-5 xl:block" aria-label="Connections navigation">
                    {sections.map((section) => (
                        <div key={section.label}>
                            <p className="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{section.label}</p>
                            <div className="space-y-0.5">
                                {section.items.map((item) => {
                                    const Icon = item.icon;
                                    const isActive = route().current(item.pattern);
                                    return (
                                        <Link
                                            key={item.route}
                                            href={itemHref(item)}
                                            aria-current={isActive ? 'page' : undefined}
                                            className={`flex h-9 items-center gap-2.5 rounded-md px-2.5 text-sm font-medium transition-colors ${
                                                isActive ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-100 hover:text-navy-900'
                                            }`}
                                        >
                                            <Icon className={`h-4 w-4 ${isActive ? 'text-brand-600' : 'text-slate-400'}`} strokeWidth={1.8} aria-hidden="true" />
                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </nav>

                <div className="min-w-0">
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h1 className="text-xl font-bold tracking-tight text-slate-900">{title}</h1>
                            {description && <p className="mt-1 max-w-3xl text-sm text-slate-500">{description}</p>}
                        </div>
                        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                    </div>
                    {children}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
