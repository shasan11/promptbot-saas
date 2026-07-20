import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Index({ users, roles = [] }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Tenant Users</h2>}>
            <Head title="Tenant Users" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <section className="rounded border border-gray-200 bg-white">
                    <div className="border-b px-4 py-3">
                        <h3 className="text-sm font-semibold text-gray-900">Users</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">Name</th>
                                    <th className="px-4 py-3">Email</th>
                                    <th className="px-4 py-3">Roles</th>
                                    <th className="px-4 py-3">Created</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {users.data.map((user) => (
                                    <tr key={user.id}>
                                        <td className="px-4 py-3 font-medium text-gray-950">{user.name}</td>
                                        <td className="px-4 py-3 text-gray-600">{user.email}</td>
                                        <td className="px-4 py-3 text-gray-600">
                                            {user.roles?.map((role) => role.label || role.name).join(', ') || 'No role'}
                                        </td>
                                        <td className="px-4 py-3 text-gray-500">
                                            {user.created_at ? new Date(user.created_at).toLocaleDateString() : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded border border-gray-200 bg-white p-4">
                    <h3 className="text-sm font-semibold text-gray-900">Available roles</h3>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {roles.map((role) => (
                            <span key={role.id} className="rounded border border-gray-200 bg-gray-50 px-3 py-1 text-sm text-gray-700">
                                {role.label || role.name}
                            </span>
                        ))}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
