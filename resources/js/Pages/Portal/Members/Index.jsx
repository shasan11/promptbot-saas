import Panel from '@/Components/Portal/Panel';
import StatusPill from '@/Components/Portal/StatusPill';
import PortalLayout from '@/Layouts/PortalLayout';
import { router, useForm, usePage } from '@inertiajs/react';

const capabilities = [
    ['can_manage_services', 'Manage workspaces'],
    ['can_manage_billing', 'Manage billing'],
    ['can_manage_members', 'Manage members'],
    ['can_manage_support', 'Manage support'],
];

function WorkspaceAccess({ data, setData, workspaces }) {
    const toggle = (id) => setData('tenant_ids', data.tenant_ids.includes(id)
        ? data.tenant_ids.filter((value) => value !== id)
        : [...data.tenant_ids, id]);

    return (
        <div className="rounded-lg border border-slate-200 p-3">
            <label className="text-sm font-medium">
                Workspace access
                <select value={data.service_access} onChange={(event) => setData('service_access', event.target.value)} className="mt-1.5 w-full rounded-lg border-slate-300">
                    <option value="all">All current and future workspaces</option>
                    <option value="selected">Selected workspaces only</option>
                </select>
            </label>
            {data.service_access === 'selected' && (
                <div className="mt-3 space-y-2">
                    {workspaces.map((workspace) => <label key={workspace.id} className="flex items-center gap-2 text-sm"><input type="checkbox" className="rounded" checked={data.tenant_ids.includes(workspace.id)} onChange={() => toggle(workspace.id)} />{workspace.company_name}</label>)}
                </div>
            )}
        </div>
    );
}

function MemberEditor({ member, grants, workspaces, canManage }) {
    const owner = member.pivot.role === 'owner';
    const form = useForm({
        role: owner ? 'owner' : member.pivot.role,
        ...Object.fromEntries(capabilities.map(([key]) => [key, !!member.pivot[key]])),
        service_access: member.pivot.service_access || 'all',
        tenant_ids: grants || [],
    });
    if (!canManage || owner) {
        return <div className="flex items-center justify-between gap-4 py-4"><div><p className="font-semibold text-slate-900">{member.name}</p><p className="text-sm text-slate-500">{member.email}</p></div><StatusPill value={member.pivot.role} /></div>;
    }

    return (
        <details className="py-4">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-4"><div><p className="font-semibold text-slate-900">{member.name}</p><p className="text-sm text-slate-500">{member.email}</p></div><StatusPill value={member.pivot.role} /></summary>
            <form onSubmit={(event) => { event.preventDefault(); form.put(route('portal.members.update', member.public_uuid), { preserveScroll: true }); }} className="mt-4 space-y-3 rounded-lg bg-slate-50 p-4">
                <select value={form.data.role} onChange={(event) => form.setData('role', event.target.value)} className="w-full rounded-lg border-slate-300">{['admin', 'billing', 'member', 'viewer'].map((role) => <option key={role}>{role}</option>)}</select>
                <div className="grid gap-2 sm:grid-cols-2">{capabilities.map(([key, label]) => <label key={key} className="flex items-center gap-2 text-sm"><input type="checkbox" className="rounded" checked={form.data[key]} onChange={(event) => form.setData(key, event.target.checked)} />{label}</label>)}</div>
                <WorkspaceAccess data={form.data} setData={form.setData} workspaces={workspaces} />
                <div className="flex justify-end gap-3"><button type="button" onClick={() => confirm(`Remove ${member.name}?`) && router.delete(route('portal.members.destroy', member.public_uuid))} className="text-sm font-semibold text-rose-600">Remove</button><button disabled={form.processing} className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save access</button></div>
            </form>
        </details>
    );
}

export default function Index({ members, invitations, workspaces = [], accessGrants = {}, invitationsEnabled = true }) {
    const membership = usePage().props.portal.membership;
    const canManage = membership?.role === 'owner' || membership?.role === 'admin' || membership?.can_manage_members;
    const canInvite = canManage && invitationsEnabled;
    const invite = useForm({ email: '', role: 'member', can_manage_services: false, can_manage_billing: false, can_manage_members: false, can_manage_support: true, service_access: 'all', tenant_ids: [] });
    const transfer = useForm({ portal_user_id: '', current_password: '' });
    const submit = (event) => { event.preventDefault(); invite.post(route('portal.members.store'), { onSuccess: () => invite.reset() }); };

    return (
        <PortalLayout title="Members">
            <div className="grid gap-6 lg:grid-cols-[1.4fr_0.8fr]">
                <div className="space-y-6">
                    <Panel title="Account members" description="Account access remains separate from helpdesk roles inside each workspace.">
                        <div className="divide-y divide-slate-100">{members.map((member) => <MemberEditor key={member.id} member={member} grants={accessGrants[member.id] || []} workspaces={workspaces} canManage={canManage} />)}</div>
                        {invitations.length > 0 && <div className="mt-5 border-t pt-5"><h3 className="text-sm font-semibold">Pending invitations</h3>{invitations.map((item) => <div key={item.id} className="mt-3 flex justify-between text-sm"><span>{item.email}</span><span className="text-slate-500">{item.role} · expires {new Date(item.expires_at).toLocaleDateString()}</span></div>)}</div>}
                    </Panel>
                    {membership?.role === 'owner' && (
                        <Panel title="Transfer ownership" description="The new owner must be a verified member. Your password confirms this sensitive action.">
                            <form onSubmit={(event) => { event.preventDefault(); transfer.post(route('portal.members.transfer')); }} className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                                <select required value={transfer.data.portal_user_id} onChange={(event) => transfer.setData('portal_user_id', event.target.value)} className="rounded-lg border-slate-300"><option value="">Choose member</option>{members.filter((member) => member.pivot.role !== 'owner').map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}</select>
                                <input required type="password" placeholder="Current password" value={transfer.data.current_password} onChange={(event) => transfer.setData('current_password', event.target.value)} className="rounded-lg border-slate-300" />
                                <button className="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Transfer</button>
                            </form>
                        </Panel>
                    )}
                </div>
                {canInvite && (
                    <Panel title="Invite member">
                        <form onSubmit={submit} className="space-y-4">
                            <label className="block text-sm font-medium">Email<input type="email" required value={invite.data.email} onChange={(event) => invite.setData('email', event.target.value)} className="mt-1.5 w-full rounded-lg border-slate-300" /></label>
                            <label className="block text-sm font-medium">Role<select value={invite.data.role} onChange={(event) => invite.setData('role', event.target.value)} className="mt-1.5 w-full rounded-lg border-slate-300">{['admin', 'billing', 'member', 'viewer'].map((role) => <option key={role}>{role}</option>)}</select></label>
                            <div className="space-y-2">{capabilities.map(([key, label]) => <label key={key} className="flex items-center gap-2 text-sm"><input type="checkbox" className="rounded" checked={invite.data[key]} onChange={(event) => invite.setData(key, event.target.checked)} />{label}</label>)}</div>
                            <WorkspaceAccess data={invite.data} setData={invite.setData} workspaces={workspaces} />
                            {Object.values(invite.errors).map((error) => <p key={error} className="text-xs text-rose-600">{error}</p>)}
                            <button disabled={invite.processing} className="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{invite.processing ? 'Sending…' : 'Send invitation'}</button>
                        </form>
                    </Panel>
                )}
            </div>
        </PortalLayout>
    );
}
