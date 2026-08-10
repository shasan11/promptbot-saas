import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { Activity, BarChart3, Bot, Braces, CheckSquare2, ClipboardCheck, Cpu, FlaskConical, LayoutGrid, Settings, SlidersHorizontal } from 'lucide-react';

const ITEMS = [
    { label: 'Overview', route: 'tenant.admin.ai.index', pattern: 'tenant.admin.ai.index', icon: LayoutGrid, permission: 'ai.view' },
    { label: 'Agents', route: 'tenant.admin.ai.agents.index', pattern: 'tenant.admin.ai.agents.*', icon: Bot, permission: 'ai.agents.view' },
    { label: 'Playground', route: 'tenant.admin.ai.playground.index', pattern: 'tenant.admin.ai.playground.*', icon: FlaskConical, permission: 'ai.playground.use' },
    { label: 'Prompts', route: 'tenant.admin.ai.prompts.index', pattern: 'tenant.admin.ai.prompts.*', icon: Braces, permission: 'ai.prompts.view' },
    { label: 'Approvals', route: 'tenant.admin.ai.approvals.index', pattern: 'tenant.admin.ai.approvals.*', icon: CheckSquare2, permission: 'ai.approvals.view' },
    { label: 'Evaluations', route: 'tenant.admin.ai.evaluations.index', pattern: 'tenant.admin.ai.evaluations.*', icon: ClipboardCheck, permission: 'ai.evaluations.view' },
    { label: 'Providers', route: 'tenant.admin.ai.providers.index', pattern: 'tenant.admin.ai.providers.*', icon: Cpu, permission: 'ai.providers.view' },
    { label: 'Usage', route: 'tenant.admin.ai.usage.index', pattern: 'tenant.admin.ai.usage.*', icon: BarChart3, permission: 'ai.usage.view' },
    { label: 'Logs', route: 'tenant.admin.ai.logs.index', pattern: 'tenant.admin.ai.logs.*', icon: Activity, permission: 'ai.logs.view' },
    { label: 'Settings', route: 'tenant.admin.ai.settings.edit', pattern: 'tenant.admin.ai.settings.*', icon: Settings, permission: 'ai.settings.manage' },
];

export default function AIShell({ title, description, actions, children }) {
    const { auth } = usePage().props;
    const items = ITEMS.filter((item) => auth?.permissions?.includes(item.permission));
    const active = items.find((item) => route().current(item.pattern)) || items[0];

    return (
        <AuthenticatedLayout title="AI platform">
            <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
                <div className="lg:hidden">
                    <Select value={active ? route(active.route) : ''} onChange={(event) => router.visit(event.target.value)}>
                        {items.map((item) => <option key={item.route} value={route(item.route)}>{item.label}</option>)}
                    </Select>
                </div>
                <nav className="hidden space-y-1 lg:block" aria-label="AI platform navigation">
                    <p className="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">AI platform</p>
                    {items.map((item) => {
                        const Icon = item.icon;
                        const selected = route().current(item.pattern);
                        return (
                            <Link key={item.route} href={route(item.route)} aria-current={selected ? 'page' : undefined}
                                className={`flex h-9 items-center gap-2.5 rounded-md px-2.5 text-sm font-medium ${selected ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-100'}`}>
                                <Icon className={`h-4 w-4 ${selected ? 'text-brand-600' : 'text-slate-400'}`} />{item.label}
                            </Link>
                        );
                    })}
                    <div className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <SlidersHorizontal className="h-4 w-4 text-slate-500" />
                        <p className="mt-2 text-xs font-semibold text-slate-700">Human-in-the-loop</p>
                        <p className="mt-1 text-xs leading-5 text-slate-500">AI output remains a suggestion unless an administrator explicitly enables a reviewed automation.</p>
                    </div>
                </nav>
                <div className="min-w-0">
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div><h1 className="text-xl font-bold tracking-tight text-slate-900">{title}</h1>{description && <p className="mt-1 max-w-3xl text-sm text-slate-500">{description}</p>}</div>
                        {actions && <div className="flex gap-2">{actions}</div>}
                    </div>
                    {children}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
