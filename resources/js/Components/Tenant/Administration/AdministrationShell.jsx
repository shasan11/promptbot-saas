import HorizontalWorkspaceShell from '@/Components/Tenant/HorizontalWorkspaceShell';
import { Building2, CalendarDays, Clock3, Layers, LayoutGrid, Settings, Shield, UserPlus, Users } from 'lucide-react';

const sections = [
    { label: 'People & access', items: [
        { label: 'Overview', route: 'tenant.admin.administration.index', pattern: 'tenant.admin.administration.index', icon: LayoutGrid, permission: 'users.view' },
        { label: 'Users', route: 'tenant.admin.administration.users.index', pattern: 'tenant.admin.administration.users.*', icon: Users, permission: 'users.view' },
        { label: 'Invitations', route: 'tenant.admin.administration.invitations.index', pattern: 'tenant.admin.administration.invitations.*', icon: UserPlus, permission: 'invitations.view' },
        { label: 'Teams', route: 'tenant.admin.administration.teams.index', pattern: 'tenant.admin.administration.teams.*', icon: Layers, permission: 'teams.view' },
        { label: 'Departments', route: 'tenant.admin.administration.departments.index', pattern: 'tenant.admin.administration.departments.*', icon: Building2, permission: 'departments.view' },
        { label: 'Roles', route: 'tenant.admin.administration.roles.index', pattern: 'tenant.admin.administration.roles.*', icon: Shield, permission: 'roles.view' },
    ] },
    { label: 'Workspace settings', items: [
        { label: 'Workspace', route: 'tenant.admin.administration.workspace.edit', routeParams: ['general'], pattern: 'tenant.admin.administration.workspace.*', icon: Settings, permission: 'workspace.view' },
        { label: 'Business hours', route: 'tenant.admin.administration.business-hours.index', pattern: 'tenant.admin.administration.business-hours.*', icon: Clock3, permission: 'workspace.view' },
        { label: 'Holidays', route: 'tenant.admin.administration.holidays.index', pattern: 'tenant.admin.administration.holidays.*', icon: CalendarDays, permission: 'workspace.view' },
    ] },
];

export default function AdministrationShell(props) {
    return <HorizontalWorkspaceShell workspace="Administration" sections={sections} {...props} />;
}
