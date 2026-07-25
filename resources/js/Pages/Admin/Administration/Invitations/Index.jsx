import ResourceIndex from '@/Pages/Admin/ResourceIndex';
import { Link } from '@inertiajs/react';

export default function InvitationsIndex({ invitations, filters }) {
    return (
        <div>
            <div className="mb-4">
                <Link className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" href={route('superadmin.administrators.invitations.create')}>Invite administrator</Link>
            </div>
            <ResourceIndex
                title="Administrator Invitations"
                table="platform_admin_invitations"
                records={invitations}
                filters={filters}
                columns={[
                    { key: 'email', label: 'Email' },
                    { key: 'status', label: 'Status' },
                    { key: 'expires_at', label: 'Expires' },
                    { key: 'accepted_at', label: 'Accepted' },
                ]}
                meta={{ description: 'Pending, accepted, expired, and revoked administrator invitations.' }}
            />
        </div>
    );
}
