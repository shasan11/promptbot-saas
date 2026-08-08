import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import FilterBar, { FilterChip } from '@/Components/UI/FilterBar';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { BadgeDollarSign } from 'lucide-react';
import { useState } from 'react';

const STATUSES = ['trial', 'active', 'past_due', 'cancelled', 'expired', 'suspended', 'manual'];

function daysRemaining(dateString) {
    if (!dateString) return null;
    const diff = Math.ceil((new Date(dateString) - new Date()) / (1000 * 60 * 60 * 24));
    return diff;
}

export default function Index({ subscriptions, plans = [], filters = {} }) {
    const [status, setStatus] = useState(filters.status || '');
    const [planId, setPlanId] = useState(filters.plan_id || '');
    const rows = subscriptions?.data || [];

    const applyFilters = (next = {}) => {
        router.get(route('superadmin.subscriptions.index'), { status, plan_id: planId, ...next }, { preserveState: true, preserveScroll: true });
    };

    const activeFilters = [
        status && { key: 'status', label: `Status: ${status.replace('_', ' ')}` },
        planId && { key: 'plan_id', label: `Plan: ${plans.find((plan) => String(plan.id) === String(planId))?.name}` },
    ].filter(Boolean);

    const columns = [
        {
            title: 'Tenant',
            dataIndex: ['tenant', 'company_name'],
            render: (value, subscription) => (
                <Link href={route('superadmin.subscriptions.show', subscription.public_uuid || subscription.id)} className="font-semibold text-slate-900 hover:text-brand-700">
                    {value || 'Unknown tenant'}
                </Link>
            ),
        },
        { title: 'Plan', dataIndex: ['plan', 'name'], render: (value) => value || '—' },
        { title: 'Status', dataIndex: 'status', render: (status) => <StatusBadge status={status} /> },
        { title: 'Billing', dataIndex: 'billing_interval', render: (value) => value || '—' },
        {
            title: 'Current period ends',
            dataIndex: 'current_period_ends_at',
            render: (value, subscription) => {
                if (!value) return '—';
                const remaining = daysRemaining(value);
                const isPastDue = subscription.status === 'past_due' || (remaining !== null && remaining < 0);
                return (
                    <div>
                        <div className="text-slate-700">{value}</div>
                        {remaining !== null && (
                            <Badge tone={isPastDue ? 'danger' : remaining <= 7 ? 'warning' : 'neutral'} className="mt-1">
                                {remaining < 0 ? `${Math.abs(remaining)}d overdue` : `${remaining}d remaining`}
                            </Badge>
                        )}
                    </div>
                );
            },
        },
    ];

    return (
        <AuthenticatedLayout header={<PageHeader title="Subscriptions" subtitle="Review tenant billing state and lifecycle dates." />}>
            <Head title="Subscriptions" />

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
                <FilterBar>
                    <Select value={status} onChange={(event) => { setStatus(event.target.value); applyFilters({ status: event.target.value }); }} className="w-48">
                        <option value="">All statuses</option>
                        {STATUSES.map((item) => <option key={item} value={item}>{item.replace('_', ' ')}</option>)}
                    </Select>
                    <Select value={planId} onChange={(event) => { setPlanId(event.target.value); applyFilters({ plan_id: event.target.value }); }} className="w-48">
                        <option value="">All plans</option>
                        {plans.map((plan) => <option key={plan.id} value={plan.id}>{plan.name}</option>)}
                    </Select>
                </FilterBar>
                {activeFilters.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                        {activeFilters.map((filter) => (
                            <FilterChip
                                key={filter.key}
                                label={filter.label}
                                onRemove={() => {
                                    if (filter.key === 'status') setStatus('');
                                    if (filter.key === 'plan_id') setPlanId('');
                                    applyFilters({ [filter.key]: '' });
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>

            <div className="mt-4">
                {rows.length ? (
                    <>
                        <DataTable columns={columns} dataSource={rows} />
                        <Pagination links={subscriptions?.links} />
                    </>
                ) : (
                    <EmptyState icon={BadgeDollarSign} title="No subscriptions found" description="Try a different status or plan filter." />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
