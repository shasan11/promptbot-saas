import CustomersShell from '@/Components/Tenant/Customers/CustomersShell';
import Badge from '@/Components/UI/Badge';
import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, ChevronRight, ListPlus, Tags, Upload, UsersRound } from 'lucide-react';

const modules = [
    { key: 'contacts', label: 'Contacts', description: 'Customer identities, ownership, communication details, and support history.', routeName: 'tenant.admin.customers.contacts.index', permission: 'customers.view', icon: UsersRound },
    { key: 'companies', label: 'Companies', description: 'Organizations, account owners, associated contacts, and activity.', routeName: 'tenant.admin.customers.companies.index', permission: 'companies.view', icon: Building2 },
    { key: 'imports', label: 'Imports', description: 'Upload customer records from CSV and review import progress or failures.', routeName: 'tenant.admin.customers.imports.index', permission: 'customers.import', icon: Upload },
    { key: 'tags', label: 'Tags', description: 'Create reusable labels for contacts, companies, and support workflows.', routeName: 'tenant.admin.customers.tags.index', permission: 'tags.manage', icon: Tags },
    { key: 'custom_fields', label: 'Custom fields', description: 'Capture workspace-specific customer information with validated fields.', routeName: 'tenant.admin.customers.custom-fields.index', permission: 'custom_fields.manage', icon: ListPlus },
];

export default function Overview({ counts = {} }) {
    const permissions = usePage().props.auth?.permissions || [];
    const visibleModules = modules.filter((module) => permissions.includes(module.permission));

    return (
        <CustomersShell title="Customer workspace" description="Manage every customer record and configuration from one place.">
            <Head title="Customers" />

            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {visibleModules.map((module) => {
                    const Icon = module.icon;
                    return (
                        <Link key={module.key} href={route(module.routeName)} className="group flex min-h-44 flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-soft transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-soft-lg">
                            <div className="flex items-start justify-between gap-3">
                                <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><Icon className="h-5 w-5" /></span>
                                {counts[module.key] !== null && counts[module.key] !== undefined && <Badge tone="neutral">{counts[module.key].toLocaleString()}</Badge>}
                            </div>
                            <div className="mt-4 flex items-center gap-2"><h2 className="flex-1 text-sm font-semibold text-slate-900 group-hover:text-brand-700">{module.label}</h2><ChevronRight className="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" /></div>
                            <p className="mt-2 text-xs leading-5 text-slate-500">{module.description}</p>
                        </Link>
                    );
                })}
            </div>
        </CustomersShell>
    );
}
