import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Dashboard({ tenant }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">{tenant.company_name}</h2>}>
            <Head title="Tenant Dashboard" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded border bg-white p-6 shadow-sm">
                        <div className="text-sm text-gray-500">Tenant ID</div>
                        <div className="mt-1 font-mono text-lg text-gray-900">{tenant.id}</div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
