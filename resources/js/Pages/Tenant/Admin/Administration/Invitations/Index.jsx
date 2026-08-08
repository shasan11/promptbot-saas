import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/Superadmin/Pagination';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, RefreshCw, UserPlus, XCircle } from 'lucide-react';

const tone = { pending: 'info', accepted: 'brand', expired: 'neutral', revoked: 'danger' };

export default function Index({ invitations }) {
    const { auth } = usePage().props;
    const canManage = auth?.permissions?.includes('invitations.create');

    return (
        <AdministrationShell
            title="Invitations"
            description="Track pending, accepted, and expired workspace invitations."
            actions={canManage && <Button href={route('tenant.admin.administration.invitations.create')} variant="brand" icon={Plus}>Invite user</Button>}
        >
            <Head title="Invitations" />

            {invitations.data.length ? (
                <>
                    <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Email</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Invited by</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Expires</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {invitations.data.map((invite) => (
                                        <tr key={invite.id} className="hover:bg-slate-50">
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-900">{invite.email}</div>
                                                {invite.name && <div className="text-xs text-slate-500">{invite.name}</div>}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{invite.inviter?.name || '—'}</td>
                                            <td className="px-4 py-3"><Badge tone={tone[invite.status] || 'neutral'}>{invite.status}</Badge></td>
                                            <td className="px-4 py-3 text-slate-500">{new Date(invite.expires_at).toLocaleDateString()}</td>
                                            <td className="px-4 py-3 text-right">
                                                {invite.status === 'pending' && (
                                                    <DropdownMenu
                                                        items={[
                                                            { label: 'Resend', icon: RefreshCw, onClick: () => router.post(route('tenant.admin.administration.invitations.resend', invite.id), {}, { preserveScroll: true }) },
                                                            { label: 'Revoke', icon: XCircle, danger: true, onClick: () => router.post(route('tenant.admin.administration.invitations.revoke', invite.id), {}, { preserveScroll: true }) },
                                                        ]}
                                                    />
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <Pagination links={invitations.links} />
                </>
            ) : (
                <EmptyState icon={UserPlus} title="No invitations sent yet" description="Invite people to join this workspace." action={canManage && <Button href={route('tenant.admin.administration.invitations.create')} variant="brand" icon={Plus}>Invite user</Button>} />
            )}
        </AdministrationShell>
    );
}
