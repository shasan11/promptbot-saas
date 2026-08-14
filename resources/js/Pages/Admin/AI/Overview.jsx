import StatCard from '@/Components/Superadmin/StatCard';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Alert from '@/Components/UI/Alert';
import { Card } from '@/Components/UI/Card';
import Button from '@/Components/UI/Button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Activity, Bot, CircuitBoard, Coins, Gauge, Layers } from 'lucide-react';

function formatNumber(value) {
    return new Intl.NumberFormat().format(value ?? 0);
}

export default function Overview({ status, metrics, providerHealth, warnings }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="AI Overview"
                    subtitle="The current state of PromptBot's AI infrastructure, sourced live from configured providers, models, and usage."
                    actions={<Link href={route('superadmin.ai.providers.index')} className="inline-flex items-center rounded-md bg-navy-800 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-navy-900">Configure providers</Link>}
                />
            }
        >
            <Head title="AI Overview" />

            <div className="space-y-6">
                {!status.master_enabled && (
                    <Alert tone="warning" title="AI is disabled">
                        Configure an AI provider and enable AI in Settings to activate PromptBot's AI features.
                        <div className="mt-3">
                            <Button as="span" href={route('superadmin.ai.settings.index')} variant="secondary" size="sm">Go to AI Settings</Button>
                        </div>
                    </Alert>
                )}

                {warnings?.length > 0 && status.master_enabled && (
                    <Alert tone="warning" title="Configuration warnings">
                        <ul className="list-disc space-y-1 pl-4">
                            {warnings.map((warning) => <li key={warning}>{warning}</li>)}
                        </ul>
                    </Alert>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="AI status" value={status.master_enabled ? 'Active' : 'Disabled'} tone={status.master_enabled ? 'emerald' : 'rose'} icon={Bot} />
                    <StatCard title="Providers configured" value={status.providers_configured} suffix={`/ ${status.providers_enabled} enabled`} tone="blue" icon={Layers} />
                    <StatCard title="Requests today" value={formatNumber(metrics.requests_today)} tone="slate" icon={Activity} />
                    <StatCard title="Requests this month" value={formatNumber(metrics.requests_this_month)} tone="slate" icon={Activity} />
                    <StatCard title="Tokens this month" value={formatNumber(metrics.tokens_this_month)} tone="slate" icon={Gauge} />
                    <StatCard
                        title="Estimated cost this month"
                        value={metrics.estimated_cost_this_month === null ? 'Cost unavailable' : `$${metrics.estimated_cost_this_month.toFixed(2)}`}
                        tone="amber"
                        icon={Coins}
                    />
                    <StatCard title="Failed requests" value={formatNumber(metrics.failed_requests_this_month)} tone="rose" icon={CircuitBoard} />
                    <StatCard title="Average latency" value={`${formatNumber(metrics.average_latency_ms)} ms`} tone="slate" icon={Gauge} />
                </div>

                <Card>
                    <div className="mb-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Active provider</div>
                            <div className="mt-1 text-sm font-semibold text-slate-900">{status.active_provider || '—'}</div>
                        </div>
                        <div>
                            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Default chat model</div>
                            <div className="mt-1 text-sm font-semibold text-slate-900">{status.default_chat_model || '—'}</div>
                        </div>
                        <div>
                            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Default embedding model</div>
                            <div className="mt-1 text-sm font-semibold text-slate-900">{status.default_embedding_model || '—'}</div>
                        </div>
                    </div>
                </Card>

                <div>
                    <h2 className="mb-3 text-sm font-bold text-slate-900">Provider health</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {providerHealth.length === 0 && (
                            <Card className="sm:col-span-2 lg:col-span-3">
                                <p className="text-sm text-slate-500">No providers have been added yet.</p>
                            </Card>
                        )}
                        {providerHealth.map((provider) => (
                            <Card key={provider.name}>
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="text-sm font-bold text-slate-900">{provider.name}</div>
                                        <div className="text-xs text-slate-500">{provider.driver_label}</div>
                                    </div>
                                    {provider.is_default && <StatusBadge status="active" />}
                                </div>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <StatusBadge status={provider.configured ? 'configured' : 'pending'} />
                                    <StatusBadge status={provider.is_enabled ? 'active' : 'cancelled'} />
                                    <StatusBadge status={provider.connection_status || 'unknown'} />
                                </div>
                            </Card>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
