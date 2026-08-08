import Pagination from '@/Components/Superadmin/Pagination';
import Badge from '@/Components/UI/Badge';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link, useForm } from '@inertiajs/react';

function CredentialRow({ credential }) {
    const rotateForm = useForm({
        credential: { api_key: '' },
        reason: '',
    });
    const revokeForm = useForm({ reason: '' });

    const rotate = (event) => {
        event.preventDefault();
        rotateForm.post(route('tenant.admin.connections.credentials.rotate', credential.id), {
            preserveScroll: true,
            onSuccess: () => rotateForm.reset(),
        });
    };

    const revoke = (event) => {
        event.preventDefault();

        if (!window.confirm('Revoke this credential?')) {
            return;
        }

        revokeForm.post(route('tenant.admin.connections.credentials.revoke', credential.id), {
            preserveScroll: true,
            onSuccess: () => revokeForm.reset(),
        });
    };

    return (
        <>
            <tr>
                <td className="px-4 py-3">
                    <Link className="font-semibold text-brand-700" href={route('tenant.admin.connections.show', credential.connection.id)}>{credential.connection.name}</Link>
                    <div className="text-xs text-slate-500">{credential.connection.integration?.name}</div>
                </td>
                <td className="px-4 py-3">{credential.type}</td>
                <td className="px-4 py-3 font-mono text-xs text-slate-500">{credential.masked_secret || 'No secret displayed'}</td>
                <td className="px-4 py-3"><Badge tone={credential.status === 'active' ? 'brand' : 'warning'}>{credential.status}</Badge></td>
                <td className="px-4 py-3 text-slate-500">{credential.last_used_at ? new Date(credential.last_used_at).toLocaleString() : 'Never used'}</td>
                <td className="px-4 py-3 text-slate-500">{credential.rotated_at ? new Date(credential.rotated_at).toLocaleString() : 'Not rotated'}</td>
            </tr>
            <tr className="bg-slate-50/70">
                <td colSpan="6" className="px-4 py-3">
                    <div className="grid gap-3 lg:grid-cols-[1fr_1fr_auto_auto]">
                        <input
                            className="rounded-md border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            type="password"
                            placeholder="New secret"
                            value={rotateForm.data.credential.api_key}
                            onChange={(event) => rotateForm.setData('credential', { api_key: event.target.value })}
                        />
                        <input
                            className="rounded-md border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            placeholder="Reason"
                            value={rotateForm.data.reason}
                            onChange={(event) => rotateForm.setData('reason', event.target.value)}
                        />
                        <button
                            className="rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 disabled:opacity-60"
                            disabled={rotateForm.processing || !rotateForm.data.credential.api_key}
                            onClick={rotate}
                        >
                            Rotate
                        </button>
                        <button
                            className="rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60"
                            disabled={revokeForm.processing || credential.status === 'revoked'}
                            onClick={revoke}
                        >
                            Revoke
                        </button>
                    </div>
                    {rotateForm.errors.credential && <div className="mt-1 text-xs text-red-600">{rotateForm.errors.credential}</div>}
                </td>
            </tr>
        </>
    );
}

export default function Index({ credentials }) {
    return (
        <ConnectionsShell title="Credentials" description="Masked credential inventory with status, last use, and rotation metadata. Raw secrets are never returned to the UI.">
            <Head title="Credentials" />
            <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Connection</th>
                            <th className="px-4 py-3">Type</th>
                            <th className="px-4 py-3">Masked Secret</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Last Used</th>
                            <th className="px-4 py-3">Rotated</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {credentials.data.map((credential) => (
                            <CredentialRow key={credential.id} credential={credential} />
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination links={credentials.links} />
        </ConnectionsShell>
    );
}
