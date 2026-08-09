import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, usePage } from '@inertiajs/react';

const tabs = [
    ['Contacts', 'tenant.admin.customers.contacts.index', 'tenant.admin.customers.contacts.*', 'customers.view'],
    ['Companies', 'tenant.admin.customers.companies.index', 'tenant.admin.customers.companies.*', 'companies.view'],
    ['Imports', 'tenant.admin.customers.imports.index', 'tenant.admin.customers.imports.*', 'customers.import'],
    ['Tags', 'tenant.admin.customers.tags.index', 'tenant.admin.customers.tags.*', 'tags.manage'],
    ['Custom fields', 'tenant.admin.customers.custom-fields.index', 'tenant.admin.customers.custom-fields.*', 'custom_fields.manage'],
];

export default function CustomersShell({ title, description, actions, children }) {
    const permissions = usePage().props.auth?.permissions || [];
    return (
        <AuthenticatedLayout title="Customers" header={(
            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div><h1 className="text-xl font-bold text-navy-900">{title}</h1>{description && <p className="mt-1 text-sm text-slate-500">{description}</p>}</div>
                    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                </div>
                <nav className="flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Customer management">
                    {tabs.filter(([, , , permission]) => permissions.includes(permission)).map(([label, routeName, active]) => (
                        <Link key={routeName} href={route(routeName)} className={`whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium ${route().current(active) ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-900'}`}>{label}</Link>
                    ))}
                </nav>
            </div>
        )}>{children}</AuthenticatedLayout>
    );
}
