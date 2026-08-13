import Panel from '@/Components/Portal/Panel';
import PortalLayout from '@/Layouts/PortalLayout';
import { router, useForm } from '@inertiajs/react';

const labels = {
    invoice_issued: 'Invoice issued', payment_successful: 'Payment successful', payment_failed: 'Payment failed',
    subscription_renewal: 'Subscription renewal', trial_ending: 'Trial ending', workspace_provisioned: 'Workspace provisioned',
    workspace_suspended: 'Workspace suspended', support_ticket_reply: 'Support ticket reply',
};

export default function Notifications({ notifications, eventChannels }) {
    const form = useForm({ event_channels: eventChannels });
    const toggle = (event, channel, value) => form.setData('event_channels', { ...form.data.event_channels, [event]: { ...form.data.event_channels[event], [channel]: value } });
    return <PortalLayout title="Notifications"><div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <Panel title="Notification center">{notifications.data.length ? <div className="divide-y">{notifications.data.map(item => <button key={item.id} onClick={() => router.post(route('portal.notifications.read', item.id))} className={`block w-full py-4 text-left ${item.read_at ? 'opacity-60' : ''}`}><p className="font-semibold text-slate-900">{item.title}</p><p className="mt-1 text-sm text-slate-600">{item.body}</p><p className="mt-1 text-xs text-slate-400">{new Date(item.created_at).toLocaleString()}</p></button>)}</div> : <p className="py-12 text-center text-sm text-slate-500">You have no notifications.</p>}</Panel>
        <Panel title="Event preferences" description="Choose Email and customer-portal notifications independently for this account."><form onSubmit={event => { event.preventDefault(); form.put(route('portal.notifications.preferences')); }}><div className="grid grid-cols-[1fr_70px_70px] gap-x-3 border-b pb-2 text-xs font-semibold uppercase text-slate-500"><span>Event</span><span className="text-center">Email</span><span className="text-center">In-app</span></div><div className="divide-y">{Object.keys(labels).map(event => <div key={event} className="grid grid-cols-[1fr_70px_70px] items-center gap-x-3 py-3 text-sm"><span>{labels[event]}</span>{['email','in_app'].map(channel => <label key={channel} className="flex justify-center"><input type="checkbox" aria-label={`${labels[event]} ${channel}`} checked={!!form.data.event_channels[event]?.[channel]} onChange={input => toggle(event, channel, input.target.checked)} className="rounded" /></label>)}</div>)}</div>{Object.values(form.errors).map(error => typeof error === 'string' && <p key={error} className="mt-2 text-xs text-rose-600">{error}</p>)}<button disabled={form.processing} className="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save preferences</button></form></Panel>
    </div></PortalLayout>;
}
