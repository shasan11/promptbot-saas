import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card, SectionCard } from '@/Components/UI/Card';
import CopyButton from '@/Components/UI/CopyButton';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import EngagementShell from '@/Components/Tenant/Engagement/EngagementShell';
import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Show({ channel, adapter, embedUrl, inboundWebhookUrl }) {
    const { auth, flash } = usePage().props;
    const [removeOpen, setRemoveOpen] = useState(false);
    const permissions = auth?.permissions || [];
    const snippet = embedUrl ? `<script async src="${embedUrl}"></script>` : '';
    const remove = () => router.delete(route('tenant.admin.channels.destroy', channel.public_uuid), { onFinish: () => setRemoveOpen(false) });

    return <EngagementShell title={channel.name} description={channel.type.replaceAll('_', ' ')} actions={<div className="flex items-center gap-2"><Badge tone={channel.status === 'active' ? 'brand' : 'neutral'}>{channel.status}</Badge>{permissions.includes('channels.update') && <Button href={route('tenant.admin.channels.edit', channel.public_uuid)} variant="secondary" icon={Pencil}>Edit</Button>}{permissions.includes('channels.delete') && <Button variant="danger" icon={Trash2} onClick={() => setRemoveOpen(true)}>Remove</Button>}</div>}>
        <Head title={channel.name} />
        {flash?.channel_secret && <div className="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-4"><p className="text-sm font-semibold text-amber-900">Copy this signing secret now. It will not be shown again.</p><div className="mt-2 flex items-center gap-2 rounded bg-white p-2 font-mono text-xs"><span className="min-w-0 flex-1 break-all">{flash.channel_secret}</span><CopyButton value={flash.channel_secret} /></div></div>}
        {adapter.configurationErrors.length > 0 && <div className="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{adapter.configurationErrors.join(' ')}</div>}
        <div className="grid gap-5 lg:grid-cols-2">
            <Card><h2 className="text-sm font-semibold">Routing</h2><dl className="mt-4 grid grid-cols-2 gap-4 text-sm"><div><dt className="text-xs uppercase text-slate-400">Team</dt><dd>{channel.team?.name || 'None'}</dd></div><div><dt className="text-xs uppercase text-slate-400">Assignee</dt><dd>{channel.default_assignee?.name || 'Unassigned'}</dd></div><div><dt className="text-xs uppercase text-slate-400">Business hours</dt><dd>{channel.business_hours?.name || '24/7'}</dd></div><div><dt className="text-xs uppercase text-slate-400">Credentials</dt><dd>{channel.credential ? `Encrypted · ${channel.credential.status}` : 'None'}</dd></div></dl></Card>
            <Card><h2 className="text-sm font-semibold">Capabilities</h2><div className="mt-4 flex flex-wrap gap-2">{adapter.capabilities.map((x) => <Badge key={x}>{x.replaceAll('_', ' ')}</Badge>)}</div></Card>
            {channel.email_settings && <SectionCard title="Email settings"><dl className="grid gap-4 text-sm sm:grid-cols-2"><div><dt className="text-xs uppercase text-slate-400">Inbox</dt><dd>{channel.email_settings.inbox_address}</dd></div><div><dt className="text-xs uppercase text-slate-400">Incoming</dt><dd className="capitalize">{channel.email_settings.incoming_provider}</dd></div><div><dt className="text-xs uppercase text-slate-400">Outgoing</dt><dd className="capitalize">{channel.email_settings.outgoing_provider.replaceAll('_', ' ')}</dd></div></dl><div className="mt-4"><p className="text-xs font-medium uppercase text-slate-400">Inbound webhook</p><div className="mt-1 flex gap-2 rounded bg-slate-50 p-2 font-mono text-xs"><span className="min-w-0 flex-1 break-all">{inboundWebhookUrl}</span><CopyButton value={inboundWebhookUrl} /></div>{permissions.includes('channels.credentials.manage') && <Button className="mt-3" size="sm" variant="secondary" onClick={() => router.post(route('tenant.admin.channels.rotate-inbound-secret', channel.public_uuid))}>Rotate signing secret</Button>}</div></SectionCard>}
            {['whatsapp', 'messenger', 'instagram', 'sms'].includes(channel.type) && (
                <SectionCard title={`${{ whatsapp: 'WhatsApp', messenger: 'Messenger', instagram: 'Instagram', sms: 'SMS' }[channel.type]} webhook`} description={channel.type === 'sms' ? "Paste this URL into the Twilio Console, under this number's \"A Message Comes In\" setting." : "Paste this URL into Meta's webhook configuration for this product, along with the verify token you set when configuring this channel."}>
                    <dl className="grid gap-4 text-sm sm:grid-cols-2">
                        {channel.type === 'whatsapp' && channel.whatsapp_settings && <>
                            <div><dt className="text-xs uppercase text-slate-400">Phone number ID</dt><dd>{channel.whatsapp_settings.phone_number_id}</dd></div>
                            <div><dt className="text-xs uppercase text-slate-400">WABA ID</dt><dd>{channel.whatsapp_settings.whatsapp_business_account_id || '—'}</dd></div>
                        </>}
                        {channel.type === 'messenger' && channel.messenger_settings && <div><dt className="text-xs uppercase text-slate-400">Page ID</dt><dd>{channel.messenger_settings.page_id}</dd></div>}
                        {channel.type === 'instagram' && channel.instagram_settings && <>
                            <div><dt className="text-xs uppercase text-slate-400">Instagram Business Account ID</dt><dd>{channel.instagram_settings.instagram_business_account_id}</dd></div>
                            <div><dt className="text-xs uppercase text-slate-400">Linked Page ID</dt><dd>{channel.instagram_settings.page_id}</dd></div>
                        </>}
                        {channel.type === 'sms' && channel.sms_settings && <div><dt className="text-xs uppercase text-slate-400">From number</dt><dd>{channel.sms_settings.from_number}</dd></div>}
                    </dl>
                    <div className="mt-4">
                        <p className="text-xs font-medium uppercase text-slate-400">Webhook callback URL</p>
                        <div className="mt-1 flex gap-2 rounded bg-slate-50 p-2 font-mono text-xs"><span className="min-w-0 flex-1 break-all">{inboundWebhookUrl}</span><CopyButton value={inboundWebhookUrl} /></div>
                    </div>
                </SectionCard>
            )}
            {channel.type === 'telegram' && (
                <SectionCard title="Telegram" description="PromptBot registers this channel's webhook with Telegram automatically — there is nothing to paste anywhere.">
                    {channel.telegram_settings?.bot_username ? (
                        <p className="text-sm font-medium text-emerald-700">Connected as @{channel.telegram_settings.bot_username}</p>
                    ) : (
                        <p className="text-sm text-amber-700">Not connected yet — save a bot token and webhook secret to connect.</p>
                    )}
                </SectionCard>
            )}
            {channel.web_chat_widget && <SectionCard title="Embed web chat" description="The public key identifies this widget and exposes no private credentials.">{embedUrl ? <><div className="flex items-start gap-2 rounded-md bg-navy-950 p-3 font-mono text-xs text-slate-100"><code className="min-w-0 flex-1 break-all">{snippet}</code><CopyButton value={snippet} /></div><p className="mt-3 text-xs text-slate-500">Leave allowed origins empty to use this widget on any website, or add origins to restrict it.</p></> : <div className="rounded-lg border border-amber-200 bg-amber-50 p-4"><p className="text-sm font-semibold text-amber-900">This widget is not publicly available</p><p className="mt-1 text-xs leading-5 text-amber-800">Set the channel status to Active before copying the embed script. Draft and disabled channels intentionally return an unavailable response.</p>{permissions.includes('channels.update') && <Button href={route('tenant.admin.channels.edit', channel.public_uuid)} className="mt-3" size="sm" variant="secondary" icon={Pencil}>Review and activate</Button>}</div>}</SectionCard>}
        </div>
        <ConfirmDialog open={removeOpen} title={`Remove ${channel.name}?`} confirmLabel="Remove channel" variant="danger" onCancel={() => setRemoveOpen(false)} onConfirm={remove}>
            Existing conversation history will be retained, but this channel will stop receiving new customer messages.
        </ConfirmDialog>
    </EngagementShell>;
}
