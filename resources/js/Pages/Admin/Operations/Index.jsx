import ResourceIndex from '@/Pages/Admin/ResourceIndex';

export default function OperationsIndex({ operations, filters }) {
    return (
        <ResourceIndex
            title="Operations"
            table="platform_operations"
            records={operations}
            filters={filters}
            columns={[
                { key: 'type', label: 'Type' },
                { key: 'status', label: 'Status' },
                { key: 'progress', label: 'Progress' },
                { key: 'tenant_id', label: 'Tenant' },
                { key: 'created_at', label: 'Created' },
            ]}
            meta={{ description: 'Queued and completed high-impact platform operations.' }}
        />
    );
}
