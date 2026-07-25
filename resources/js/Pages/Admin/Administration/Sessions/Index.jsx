import ResourceIndex from '@/Pages/Admin/ResourceIndex';

export default function SessionsIndex({ sessions }) {
    return <ResourceIndex title="Sessions" table="platform_admin_sessions" records={sessions} columns={[{ key: 'administrator_id', label: 'Administrator' }, { key: 'ip_address', label: 'IP' }, { key: 'last_seen_at', label: 'Last Seen' }, { key: 'revoked_at', label: 'Revoked' }]} />;
}
