import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function ConsolePage({ title, subtitle, cards = [], sections = [], rows = [] }) {
    const columns = rows.length
        ? Object.keys(rows[0]).map((key) => ({
            title: key,
            dataIndex: key,
            render: (value) => key.toLowerCase().includes('status') || key.toLowerCase().includes('result') || key.toLowerCase().includes('severity')
                ? <StatusBadge status={value} />
                : value,
        }))
        : [];

    return (
        <AuthenticatedLayout header={<PageHeader title={title} subtitle={subtitle} />}>
            <Head title={title} />

            {cards.length > 0 && (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <div key={`${card.label}-${card.value}`} className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <div className="text-sm font-medium text-slate-500">{card.label}</div>
                                    <div className="mt-2 text-2xl font-bold tracking-tight text-slate-950">{card.value}</div>
                                </div>
                                {card.status && <StatusBadge status={card.status} />}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {sections.length > 0 && (
                <div className="mt-6 grid gap-6 xl:grid-cols-2">
                    {sections.map((section) => (
                        <section key={section.title} className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 className="text-base font-bold text-slate-950">{section.title}</h2>
                            {section.description && <p className="mt-2 text-sm leading-6 text-slate-500">{section.description}</p>}
                            {section.items?.length > 0 && (
                                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                    {section.items.map((item) => (
                                        <div key={item} className="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                                            {item}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    ))}
                </div>
            )}

            {rows.length > 0 && (
                <div className="mt-6">
                    <DataTable rowKey={(row) => Object.values(row).join('-')} columns={columns} dataSource={rows} />
                </div>
            )}

            {!cards.length && !sections.length && !rows.length && (
                <div className="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
                    Nothing to show yet.
                </div>
            )}
        </AuthenticatedLayout>
    );
}
