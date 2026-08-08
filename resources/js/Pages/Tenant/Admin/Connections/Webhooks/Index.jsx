import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, router } from '@inertiajs/react';
import { Globe2, RotateCcw } from 'lucide-react';

export default function Index({ endpoints, events }) {
    const replayable = ['failed', 'processing_failed', 'replay_failed'];

    return (
        <ConnectionsShell title="Webhooks" description="Inbound endpoints, event deduplication, replay readiness, and delivery diagnostics.">
            <Head title="Webhooks" />
            <div className="grid gap-6 xl:grid-cols-2">
                <SectionCard title="Endpoints">
                    {endpoints.data.length ? (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {endpoints.data.map((endpoint) => (
                                <li key={endpoint.id} className="flex justify-between py-3">
                                    <span className="font-semibold text-slate-800">{endpoint.name}</span>
                                    <span className="text-slate-500">{endpoint.events_count} events</span>
                                </li>
                            ))}
                        </ul>
                    ) : <EmptyState icon={Globe2} title="No webhook endpoints" description="Create an inbound webhook connection to receive signed provider events." />}
                </SectionCard>
                <SectionCard title="Recent events">
                    {events.length ? (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {events.map((event) => (
                                <li key={event.id} className="flex items-center justify-between gap-3 py-3">
                                    <div>
                                        <p className="font-medium text-slate-800">{event.event_type || event.provider_event_id}</p>
                                        <p className="text-xs text-slate-500">{event.endpoint?.name} - {event.status} - {event.payload_size || 0} bytes - {event.attempts?.length || 0} attempts</p>
                                        {event.replayed_at && <p className="mt-1 text-xs text-slate-500">Replay queued {new Date(event.replayed_at).toLocaleString()}</p>}
                                    </div>
                                    {replayable.includes(event.status) && (
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            icon={RotateCcw}
                                            onClick={() => router.post(route('tenant.admin.connections.webhooks.events.replay', event.id), {}, { preserveScroll: true })}
                                        >
                                            Replay
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    ) : <EmptyState icon={Globe2} title="No webhook events received yet" description="Signed, deduplicated events will appear here." />}
                </SectionCard>
            </div>
        </ConnectionsShell>
    );
}
