import ResourceIndex from '@/Pages/Admin/ResourceIndex';

export default function AdministratorsIndex({ administrators, filters }) {
    return (
        <ResourceIndex
            title="Administrators"
            table="central_users"
            records={administrators}
            filters={filters}
            columns={[
                { key: 'name', label: 'Name' },
                { key: 'email', label: 'Email' },
                { key: 'role', label: 'Legacy Role' },
                { key: 'is_active', label: 'Active', type: 'boolean' },
                { key: 'last_login_at', label: 'Last Login' },
            ]}
            meta={{ description: 'Platform administrator accounts and access state.' }}
        />
    );
}
