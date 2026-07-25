import ResourceIndex from '@/Pages/Admin/ResourceIndex';

export default function LoginAttemptsIndex({ attempts }) {
    return <ResourceIndex title="Login Attempts" table="platform_admin_login_attempts" records={attempts} columns={[{ key: 'email', label: 'Email' }, { key: 'successful', label: 'Successful', type: 'boolean' }, { key: 'failure_reason', label: 'Failure' }, { key: 'ip_address', label: 'IP' }, { key: 'attempted_at', label: 'Attempted' }]} />;
}
