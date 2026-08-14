import HorizontalWorkspaceShell from '@/Components/Tenant/HorizontalWorkspaceShell';
import { Building2, LayoutGrid, ListPlus, Tags, Upload, UsersRound } from 'lucide-react';

const items = [
    { label: 'Overview', routeName: 'tenant.admin.customers.index', active: 'tenant.admin.customers.index', permissions: ['customers.view', 'companies.view', 'customers.import', 'tags.manage', 'custom_fields.manage'], icon: LayoutGrid },
    { label: 'Contacts', routeName: 'tenant.admin.customers.contacts.index', active: 'tenant.admin.customers.contacts.*', permission: 'customers.view', icon: UsersRound },
    { label: 'Companies', routeName: 'tenant.admin.customers.companies.index', active: 'tenant.admin.customers.companies.*', permission: 'companies.view', icon: Building2 },
    { label: 'Imports', routeName: 'tenant.admin.customers.imports.index', active: 'tenant.admin.customers.imports.*', permission: 'customers.import', icon: Upload },
    { label: 'Tags', routeName: 'tenant.admin.customers.tags.index', active: 'tenant.admin.customers.tags.*', permission: 'tags.manage', icon: Tags },
    { label: 'Custom fields', routeName: 'tenant.admin.customers.custom-fields.index', active: 'tenant.admin.customers.custom-fields.*', permission: 'custom_fields.manage', icon: ListPlus },
];

export default function CustomersShell(props) {
    return <HorizontalWorkspaceShell workspace="Customers" items={items} {...props} />;
}
