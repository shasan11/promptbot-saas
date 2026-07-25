import ResourceIndex from '@/Pages/Admin/ResourceIndex';

export default function RolesIndex({ roles }) {
    return <ResourceIndex title="Roles" table="platform_roles" records={roles} columns={[{ key: 'name', label: 'Role' }, { key: 'guard_name', label: 'Guard' }, { key: 'permissions_count', label: 'Permissions' }]} />;
}
