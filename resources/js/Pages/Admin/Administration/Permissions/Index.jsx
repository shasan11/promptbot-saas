import ResourceIndex from '@/Pages/Admin/ResourceIndex';

export default function PermissionsIndex({ permissions }) {
    return <ResourceIndex title="Permissions" table="platform_permissions" records={permissions} columns={[{ key: 'name', label: 'Permission' }, { key: 'guard_name', label: 'Guard' }, { key: 'created_at', label: 'Created' }]} />;
}
