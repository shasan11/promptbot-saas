import Button from '@/Components/UI/Button';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import EngagementShell from '@/Components/Tenant/Engagement/EngagementShell';
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

function ErrorSummary({ errors }) {
    const messages = Object.entries(errors);
    if (!messages.length) return null;

    return (
        <div id="channel-form-errors" role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-4">
            <div className="flex gap-3">
                <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-rose-600" />
                <div>
                    <h2 className="text-sm font-bold text-rose-900">Channel could not be saved</h2>
                    <p className="mt-1 text-xs text-rose-700">Review the highlighted configuration and try again.</p>
                    <ul className="mt-2 list-disc space-y-1 pl-4 text-xs text-rose-700">
                        {messages.map(([key, message]) => <li key={key}>{message}</li>)}
                    </ul>
                </div>
            </div>
        </div>
    );
}

export default function Form({ channel, selectedType, catalog, teams, users, businessHours, hasCredentials, knowledgeBases = [] }) {
    const editing = Boolean(channel);
    const email = channel?.email_settings || {};
    const widget = channel?.web_chat_widget || {};
    const form = useForm({
        type: channel?.type || selectedType,
        name: channel?.name || '',
        status: channel?.status || 'draft',
        team_id: channel?.team_id || '',
        default_assignee_id: channel?.default_assignee_id || '',
        business_hours_policy_id: channel?.business_hours_policy_id || '',
        auto_reply_enabled: channel?.auto_reply_enabled || false,
        signature: channel?.signature || '',
        email: {
            inbox_address: email.inbox_address || '',
            incoming_provider: email.incoming_provider || 'webhook',
            outgoing_provider: email.outgoing_provider || 'laravel_mail',
            from_name: email.from_name || '',
            reply_to_address: email.reply_to_address || '',
        },
        credentials: { host: '', port: 587, username: '', password: '', encryption: 'tls' },
        widget: {
            widget_name: widget.widget_name || '',
            primary_color: widget.primary_color || '#2563eb',
            launcher_position: widget.launcher_position || 'right',
            welcome_message: widget.welcome_message || 'How can we help?',
            offline_message: widget.offline_message || 'We are currently offline. Leave a message and we will get back to you.',
            supported_languages: widget.supported_languages || ['en'],
            allowed_origins: widget.allowed_origins || [],
            privacy_url: widget.privacy_url || '',
            terms_url: widget.terms_url || '',
            allow_attachments: widget.allow_attachments ?? true,
            require_email: widget.require_email ?? true,
            require_name: widget.require_name ?? true,
            ai_auto_reply_enabled: widget.ai_auto_reply_enabled ?? false,
            knowledge_base_id: widget.knowledge_base_id || '',
        },
    });

    const setNested = (group, key, value) => form.setData(group, { ...form.data[group], [key]: value });
    const selected = catalog.find((item) => item.type === form.data.type);

    const submit = (event) => {
        event.preventDefault();
        form.clearErrors();
        const options = {
            preserveScroll: true,
            onError: () => requestAnimationFrame(() => document.getElementById('channel-form-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' })),
        };

        if (editing) {
            form.put(route('tenant.admin.channels.update', channel.public_uuid), options);
        } else {
            form.post(route('tenant.admin.channels.store'), options);
        }
    };

    return (
        <EngagementShell
            title={editing ? `Edit ${channel.name}` : 'Add channel'}
            description="Configure routing and provider settings. Credentials are encrypted and never returned after saving."
        >
            <Head title={editing ? 'Edit channel' : 'Add channel'} />

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-5">
                <ErrorSummary errors={form.errors} />

                <section className="grid gap-5 rounded-lg border border-slate-200 bg-white p-5 shadow-soft md:grid-cols-2">
                    <div className="md:col-span-2">
                        <h2 className="text-sm font-bold text-slate-900">Channel details</h2>
                        <p className="mt-1 text-xs text-slate-500">Choose how this channel is identified, routed, and made available.</p>
                    </div>

                    <FormField label="Channel type" required error={form.errors.type}>
                        <Select value={form.data.type} disabled={editing} onChange={(event) => form.setData('type', event.target.value)}>
                            {catalog.map((item) => <option key={item.type} value={item.type}>{item.label}{item.available ? '' : ' — provider unavailable'}</option>)}
                        </Select>
                        {selected && !selected.available && <p className="mt-2 text-xs font-medium text-amber-700">This provider adapter is not installed, so this channel type cannot be created yet.</p>}
                    </FormField>

                    <FormField label="Name" required error={form.errors.name}>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                    </FormField>
                    <FormField label="Status" hint="Draft channels are saved privately and cannot receive public traffic." error={form.errors.status}>
                        <Select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                            <option value="draft">Draft</option><option value="active">Active</option><option value="disabled">Disabled</option>
                        </Select>
                    </FormField>
                    <FormField label="Team" error={form.errors.team_id}>
                        <Select value={form.data.team_id} onChange={(event) => form.setData('team_id', event.target.value)}>
                            <option value="">No default team</option>{teams.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                        </Select>
                    </FormField>
                    <FormField label="Default assignee" error={form.errors.default_assignee_id}>
                        <Select value={form.data.default_assignee_id} onChange={(event) => form.setData('default_assignee_id', event.target.value)}>
                            <option value="">Unassigned</option>{users.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                        </Select>
                    </FormField>
                    <FormField label="Business hours" error={form.errors.business_hours_policy_id}>
                        <Select value={form.data.business_hours_policy_id} onChange={(event) => form.setData('business_hours_policy_id', event.target.value)}>
                            <option value="">Always available</option>{businessHours.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                        </Select>
                    </FormField>
                    <label className="flex items-center gap-2 self-end pb-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" checked={form.data.auto_reply_enabled} onChange={(event) => form.setData('auto_reply_enabled', event.target.checked)} className="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                        Enable automatic replies
                    </label>
                </section>

                {form.data.type === 'email' && (
                    <section className="grid gap-5 rounded-lg border border-slate-200 bg-white p-5 shadow-soft md:grid-cols-2">
                        <div className="md:col-span-2"><h2 className="text-sm font-bold text-slate-900">Email configuration</h2><p className="mt-1 text-xs text-slate-500">Set the receiving address and outbound delivery method.</p></div>
                        <FormField label="Inbox address" required error={form.errors['email.inbox_address']}><Input type="email" value={form.data.email.inbox_address} onChange={(event) => setNested('email', 'inbox_address', event.target.value)} /></FormField>
                        <FormField label="From name" error={form.errors['email.from_name']}><Input value={form.data.email.from_name} onChange={(event) => setNested('email', 'from_name', event.target.value)} /></FormField>
                        <FormField label="Incoming provider" error={form.errors['email.incoming_provider']}><Select value={form.data.email.incoming_provider} onChange={(event) => setNested('email', 'incoming_provider', event.target.value)}><option value="webhook">Signed inbound webhook</option><option value="imap">IMAP (server extension required)</option></Select></FormField>
                        <FormField label="Outgoing provider" error={form.errors['email.outgoing_provider']}><Select value={form.data.email.outgoing_provider} onChange={(event) => setNested('email', 'outgoing_provider', event.target.value)}><option value="laravel_mail">Application mail configuration</option><option value="smtp">Channel-specific SMTP</option></Select></FormField>
                        <FormField label="Reply-to" error={form.errors['email.reply_to_address']}><Input type="email" value={form.data.email.reply_to_address} onChange={(event) => setNested('email', 'reply_to_address', event.target.value)} /></FormField>
                        <FormField label="Signature" error={form.errors.signature}><Textarea value={form.data.signature} onChange={(event) => form.setData('signature', event.target.value)} /></FormField>

                        {form.data.email.outgoing_provider === 'smtp' && <>
                            <p className="md:col-span-2 text-xs text-slate-500">{hasCredentials ? 'Leave the secret fields blank to keep the current encrypted credentials.' : 'All SMTP credential fields are required for a new SMTP channel.'}</p>
                            <FormField label="SMTP host" required={!hasCredentials} error={form.errors['credentials.host']}><Input value={form.data.credentials.host} onChange={(event) => setNested('credentials', 'host', event.target.value)} /></FormField>
                            <FormField label="Port" required={!hasCredentials} error={form.errors['credentials.port']}><Input type="number" value={form.data.credentials.port} onChange={(event) => setNested('credentials', 'port', event.target.value)} /></FormField>
                            <FormField label="Username" required={!hasCredentials} error={form.errors['credentials.username']}><Input autoComplete="off" value={form.data.credentials.username} onChange={(event) => setNested('credentials', 'username', event.target.value)} /></FormField>
                            <FormField label="Password" required={!hasCredentials} error={form.errors['credentials.password']}><Input type="password" autoComplete="new-password" value={form.data.credentials.password} onChange={(event) => setNested('credentials', 'password', event.target.value)} /></FormField>
                            <FormField label="Encryption" error={form.errors['credentials.encryption']}><Select value={form.data.credentials.encryption} onChange={(event) => setNested('credentials', 'encryption', event.target.value)}><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></Select></FormField>
                        </>}
                    </section>
                )}

                {form.data.type === 'web_chat' && (
                    <section className="grid gap-5 rounded-lg border border-slate-200 bg-white p-5 shadow-soft md:grid-cols-2">
                        <div className="md:col-span-2"><h2 className="text-sm font-bold text-slate-900">Widget configuration</h2><p className="mt-1 text-xs text-slate-500">Control where the widget runs and what customers see.</p></div>
                        <FormField label="Widget name" required error={form.errors['widget.widget_name']}><Input value={form.data.widget.widget_name} onChange={(event) => setNested('widget', 'widget_name', event.target.value)} /></FormField>
                        <FormField label="Brand color" error={form.errors['widget.primary_color']}><Input type="color" className="h-10 p-1" value={form.data.widget.primary_color} onChange={(event) => setNested('widget', 'primary_color', event.target.value)} /></FormField>
                        <FormField label="Launcher position" error={form.errors['widget.launcher_position']}><Select value={form.data.widget.launcher_position} onChange={(event) => setNested('widget', 'launcher_position', event.target.value)}><option value="right">Right</option><option value="left">Left</option></Select></FormField>
                        <FormField label="Allowed origins" hint="Comma-separated website URLs" error={form.errors['widget.allowed_origins'] || form.errors['widget.allowed_origins.0']}><Input value={form.data.widget.allowed_origins.join(', ')} onChange={(event) => setNested('widget', 'allowed_origins', event.target.value.split(',').map((item) => item.trim()).filter(Boolean))} /></FormField>
                        <FormField label="Welcome message" error={form.errors['widget.welcome_message']} className="md:col-span-2"><Input value={form.data.widget.welcome_message} onChange={(event) => setNested('widget', 'welcome_message', event.target.value)} /></FormField>
                        <FormField label="Offline message" error={form.errors['widget.offline_message']} className="md:col-span-2"><Input value={form.data.widget.offline_message} onChange={(event) => setNested('widget', 'offline_message', event.target.value)} /></FormField>
                        <FormField label="Privacy URL" optional error={form.errors['widget.privacy_url']}><Input type="url" value={form.data.widget.privacy_url} onChange={(event) => setNested('widget', 'privacy_url', event.target.value)} /></FormField>
                        <FormField label="Terms URL" optional error={form.errors['widget.terms_url']}><Input type="url" value={form.data.widget.terms_url} onChange={(event) => setNested('widget', 'terms_url', event.target.value)} /></FormField>
                        <div className="flex flex-wrap gap-5 md:col-span-2">
                            {[['require_name', 'Require name'], ['require_email', 'Require email'], ['allow_attachments', 'Allow attachments']].map(([key, label]) => <label key={key} className="flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" checked={form.data.widget[key]} onChange={(event) => setNested('widget', key, event.target.checked)} className="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />{label}</label>)}
                        </div>
                    </section>
                )}

                {form.data.type === 'web_chat' && (
                    <section className="grid gap-5 rounded-lg border border-slate-200 bg-white p-5 shadow-soft md:grid-cols-2">
                        <div className="md:col-span-2">
                            <h2 className="text-sm font-bold text-slate-900">AI auto-reply</h2>
                            <p className="mt-1 text-xs text-slate-500">When enabled, visitor messages are answered automatically from the selected knowledge base, grounded only in its content, before a human agent ever sees them. Requires AI and Knowledge Answers to be enabled by your platform administrator.</p>
                        </div>
                        <label className="flex items-center gap-2 text-sm font-medium text-slate-700 md:col-span-2"><input type="checkbox" checked={form.data.widget.ai_auto_reply_enabled} onChange={(event) => setNested('widget', 'ai_auto_reply_enabled', event.target.checked)} className="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />Automatically answer with AI</label>
                        <FormField label="Knowledge base" hint="Required for AI auto-reply to run." error={form.errors['widget.knowledge_base_id']}>
                            <Select value={form.data.widget.knowledge_base_id} onChange={(event) => setNested('widget', 'knowledge_base_id', event.target.value)}>
                                <option value="">Select a knowledge base…</option>
                                {knowledgeBases.map((base) => <option key={base.id} value={base.id}>{base.name}</option>)}
                            </Select>
                        </FormField>
                    </section>
                )}

                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button href={route('tenant.admin.channels.index')} variant="secondary">Cancel</Button>
                    <Button type="submit" variant="brand" loading={form.processing} disabled={!selected?.available}>{editing ? 'Save channel' : 'Create channel'}</Button>
                </div>
            </form>
        </EngagementShell>
    );
}
