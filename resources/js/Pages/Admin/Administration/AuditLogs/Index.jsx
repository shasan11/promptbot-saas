import ResourceIndex from '@/Pages/Admin/ResourceIndex';

export default function AuditLogsIndex({ logs }) {
    return <ResourceIndex title="Audit Logs" table="audit_logs" records={logs} columns={[{ key: 'action', label: 'Action' }, { key: 'entity_type', label: 'Entity' }, { key: 'entity_id', label: 'Entity ID' }, { key: 'severity', label: 'Severity' }, { key: 'created_at', label: 'Created' }]} />;
}
