import EngagementShell from '@/Components/Tenant/Engagement/EngagementShell';
import Button from '@/Components/UI/Button';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

function ErrorSummary({ errors }) {
    const messages = Object.entries(errors);
    if (!messages.length) return null;

    return (
        <div id="bot-profile-form-errors" role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-4">
            <div className="flex gap-3">
                <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-rose-600" />
                <div>
                    <h2 className="text-sm font-bold text-rose-900">Bot profile could not be saved</h2>
                    <ul className="mt-2 list-disc space-y-1 pl-4 text-xs text-rose-700">
                        {messages.map(([key, message]) => <li key={key}>{message}</li>)}
                    </ul>
                </div>
            </div>
        </div>
    );
}

function Toggle({ checked, onChange, label, hint }) {
    return (
        <label className="flex items-start gap-2.5 rounded-lg border border-slate-200 p-3">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
            <span>
                <span className="block text-sm font-medium text-slate-700">{label}</span>
                <span className="mt-0.5 block text-[11px] leading-4 text-slate-500">{hint}</span>
            </span>
        </label>
    );
}

export default function Form({ profile, teams = [], defaults }) {
    const editing = Boolean(profile);
    const form = useForm({
        name: profile?.name || '',
        tone: profile?.tone || defaults.tone,
        response_length: profile?.response_length || defaults.response_length,
        language_policy: profile?.language_policy || defaults.language_policy,
        default_language: profile?.default_language || defaults.default_language,
        escalate_on_request: profile?.escalate_on_request ?? defaults.escalate_on_request,
        escalate_after_failures: profile?.escalate_after_failures ?? defaults.escalate_after_failures,
        escalate_on_negative_sentiment: profile?.escalate_on_negative_sentiment ?? defaults.escalate_on_negative_sentiment,
        escalate_on_risk_flags: profile?.escalate_on_risk_flags ?? defaults.escalate_on_risk_flags,
        escalation_team_id: profile?.escalation_team_id || '',
        is_default: profile?.is_default ?? false,
    });

    const submit = (event) => {
        event.preventDefault();
        form.clearErrors();
        const options = {
            preserveScroll: true,
            onError: () => requestAnimationFrame(() => document.getElementById('bot-profile-form-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' })),
        };

        if (editing) {
            form.put(route('tenant.admin.bot-profiles.update', profile.public_uuid), options);
        } else {
            form.post(route('tenant.admin.bot-profiles.store'), options);
        }
    };

    const destroy = () => {
        const attached = profile.channels_count || 0;
        const warning = attached > 0
            ? `${attached} channel(s) use this profile and will revert to the built-in defaults. Delete it?`
            : 'Delete this bot profile?';

        if (window.confirm(warning)) {
            form.delete(route('tenant.admin.bot-profiles.destroy', profile.public_uuid));
        }
    };

    return (
        <EngagementShell
            title={editing ? `Edit ${profile.name}` : 'New bot profile'}
            description="How the bot behaves — the same behaviour wherever it runs. Customer-facing wording stays on each channel, because it is channel-specific."
        >
            <Head title={editing ? 'Edit bot profile' : 'New bot profile'} />

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-5">
                <ErrorSummary errors={form.errors} />

                <section className="grid gap-5 rounded-lg border border-slate-200 bg-white p-5 shadow-soft md:grid-cols-2">
                    <div className="md:col-span-2">
                        <h2 className="text-sm font-bold text-slate-900">Voice</h2>
                        <p className="mt-1 text-xs text-slate-500">Guidance handed to the answer generator on every reply.</p>
                    </div>

                    <FormField label="Profile name" required error={form.errors.name}>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Support bot" />
                    </FormField>
                    <FormField label="Tone" error={form.errors.tone}>
                        <Select value={form.data.tone} onChange={(event) => form.setData('tone', event.target.value)}>
                            <option value="professional">Professional — courteous and neutral</option>
                            <option value="friendly">Friendly — warm, like a helpful colleague</option>
                            <option value="casual">Casual — plain, everyday language</option>
                        </Select>
                    </FormField>
                    <FormField label="Answer length" error={form.errors.response_length}>
                        <Select value={form.data.response_length} onChange={(event) => form.setData('response_length', event.target.value)}>
                            <option value="short">Short — one or two sentences</option>
                            <option value="balanced">Balanced — two to four sentences</option>
                            <option value="detailed">Detailed — caveats and next steps</option>
                        </Select>
                    </FormField>
                    <FormField label="Language" error={form.errors.language_policy}>
                        <Select value={form.data.language_policy} onChange={(event) => form.setData('language_policy', event.target.value)}>
                            <option value="match_customer">Reply in the customer's language</option>
                            <option value="always_default">Always reply in the default language</option>
                        </Select>
                    </FormField>
                    <FormField label="Default language" hint="Used when the customer's language cannot be detected." error={form.errors.default_language}>
                        <Input value={form.data.default_language} onChange={(event) => form.setData('default_language', event.target.value)} placeholder="en" />
                    </FormField>
                </section>

                <section className="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-soft md:grid-cols-2">
                    <div className="md:col-span-2">
                        <h2 className="text-sm font-bold text-slate-900">Handing over to a teammate</h2>
                        <p className="mt-1 text-xs text-slate-500">Once a conversation is handed over, the bot stops replying to it — a teammate owns the thread from then on.</p>
                    </div>

                    <Toggle
                        label="When the customer asks for a person"
                        hint="Recognised from the message itself, before any answer is generated. Turning this off means a customer asking for a human keeps getting the bot."
                        checked={form.data.escalate_on_request}
                        onChange={(value) => form.setData('escalate_on_request', value)}
                    />
                    <Toggle
                        label="When the customer sounds unhappy"
                        hint="Negative sentiment combined with high urgency, from the conversation classifier."
                        checked={form.data.escalate_on_negative_sentiment}
                        onChange={(value) => form.setData('escalate_on_negative_sentiment', value)}
                    />
                    <Toggle
                        label="On risk flags"
                        hint="Chargebacks, legal threats, cancellations and similar flags raised by the classifier."
                        checked={form.data.escalate_on_risk_flags}
                        onChange={(value) => form.setData('escalate_on_risk_flags', value)}
                    />
                    <FormField label="After this many unanswered questions" hint="0 turns this off — the bot will keep trying indefinitely." error={form.errors.escalate_after_failures}>
                        <Input type="number" min="0" max="10" value={form.data.escalate_after_failures} onChange={(event) => form.setData('escalate_after_failures', event.target.value)} />
                    </FormField>
                    <FormField label="Hand over to" hint="Escalations go here specifically. Conversations that already have an owner are never reassigned." error={form.errors.escalation_team_id} className="md:col-span-2">
                        <Select value={form.data.escalation_team_id} onChange={(event) => form.setData('escalation_team_id', event.target.value)}>
                            <option value="">Use the normal routing rules</option>
                            {teams.map((team) => <option key={team.id} value={team.id}>{team.name}</option>)}
                        </Select>
                    </FormField>
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                    <Toggle
                        label="Use this profile for channels with none attached"
                        hint="Without a default profile, those channels run on the built-in behaviour instead."
                        checked={form.data.is_default}
                        onChange={(value) => form.setData('is_default', value)}
                    />
                </section>

                <div className="flex items-center justify-between gap-3">
                    <div>
                        {editing && <Button type="button" variant="danger" onClick={destroy} disabled={form.processing}>Delete profile</Button>}
                    </div>
                    <div className="flex gap-3">
                        <Button type="button" variant="secondary" href={route('tenant.admin.bot-profiles.index')}>Cancel</Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>{editing ? 'Save changes' : 'Create profile'}</Button>
                    </div>
                </div>
            </form>
        </EngagementShell>
    );
}
