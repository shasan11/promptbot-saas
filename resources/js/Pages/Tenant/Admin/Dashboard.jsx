import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function Stat({ label, value }) {
    return (
        <div className="rounded border border-gray-200 bg-white p-4">
            <div className="text-sm font-medium text-gray-500">{label}</div>
            <div className="mt-2 text-3xl font-semibold text-gray-950">{value}</div>
        </div>
    );
}

export default function Dashboard({ tenant, stats = {}, recentUsers = [] }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">{tenant.company_name} Admin</h2>}>
            <Head title="Tenant Admin" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat label="Users" value={stats.users ?? 0} />
                    <Stat label="Roles" value={stats.roles ?? 0} />
                    <Stat label="Permissions" value={stats.permissions ?? 0} />
                    <Stat label="Settings" value={stats.settings ?? 0} />
                </div>

                <section className="rounded border border-gray-200 bg-white">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h3 className="text-sm font-semibold text-gray-900">Recent users</h3>
                        <Link href={route('tenant.admin.users.index')} className="text-sm font-medium text-indigo-700">
                            Manage users
                        </Link>
                    </div>
                    <div className="divide-y">
                        {recentUsers.map((user) => (
                            <div key={user.id} className="grid gap-2 px-4 py-3 text-sm sm:grid-cols-[1fr_220px]">
                                <div>
                                    <div className="font-medium text-gray-950">{user.name}</div>
                                    <div className="text-gray-500">{user.email}</div>
                                </div>
                                <div className="text-gray-600">
                                    {user.roles?.map((role) => role.label || role.name).join(', ') || 'No role'}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
