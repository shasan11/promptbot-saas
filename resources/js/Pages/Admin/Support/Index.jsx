import ResourceIndex from '@/Pages/Admin/ResourceIndex';

export default function SupportIndex({ tickets, filters }) {
    return <ResourceIndex title="Support Tickets" table="support_tickets" records={tickets} filters={filters} columns={[{ key: 'tenant_id', label: 'Tenant' }, { key: 'subject', label: 'Subject' }, { key: 'status', label: 'Status' }, { key: 'priority', label: 'Priority' }, { key: 'sla_due_at', label: 'SLA Due' }]} />;
}
