import AIShell from '@/Components/AI/AIShell';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import { router, useForm } from '@inertiajs/react';
import { CheckCircle2, KeyRound, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

const empty = { name: '', provider: 'openai', enabled: false, api_key: '', base_url: '', organization: '', default_chat_model: '', default_fast_model: '', default_reasoning_model: '', default_embedding_model: '', temperature: 0.2, top_p: 1, max_tokens: 1200, pricing_currency: 'USD', input_cost_per_million: '', output_cost_per_million: '', cached_input_cost_per_million: '', reasoning_cost_per_million: '' };

export default function Providers({ providers, catalogue, canManage }) {
    const [editing, setEditing] = useState(null);
    const form = useForm(empty);
    const definition = catalogue.find((item) => item.key === form.data.provider);

    const edit = (provider) => {
        setEditing(provider.public_uuid);
        const pricing = provider.configuration?.pricing?.models?.[provider.default_chat_model] || {};
        form.setData({ ...empty, ...provider, api_key: '', temperature: provider.configuration?.parameters?.temperature ?? 0.2, top_p: provider.configuration?.parameters?.top_p ?? 1, max_tokens: provider.configuration?.parameters?.max_tokens ?? 1200, pricing_currency: pricing.currency || 'USD', input_cost_per_million: pricing.input_per_million ?? '', output_cost_per_million: pricing.output_per_million ?? '', cached_input_cost_per_million: pricing.cached_input_per_million ?? '', reasoning_cost_per_million: pricing.reasoning_per_million ?? '' });
    };
    const reset = () => { setEditing(null); form.setData(empty); form.clearErrors(); };
    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        editing ? form.put(route('tenant.admin.ai.providers.update', editing), options) : form.post(route('tenant.admin.ai.providers.store'), options);
    };

    return (
        <AIShell title="AI providers" description="Connect tenant-owned model providers. Credentials are encrypted at rest and are never returned after save."
            actions={canManage && editing && <Button variant="secondary" icon={Plus} onClick={reset}>New provider</Button>}>
            {canManage && <SectionCard title={editing ? 'Edit provider' : 'Add provider'} description="A provider must pass its connection test before production agent deployment.">
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium text-slate-700">Display name<Input className="mt-1" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} required /></label>
                        <label className="text-sm font-medium text-slate-700">Provider<Select className="mt-1" value={form.data.provider} onChange={(e) => form.setData('provider', e.target.value)}>{catalogue.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</Select></label>
                        <label className="text-sm font-medium text-slate-700">Default chat model<Input className="mt-1" value={form.data.default_chat_model} onChange={(e) => form.setData('default_chat_model', e.target.value)} placeholder="Model identifier from your provider" required /></label>
                        <label className="text-sm font-medium text-slate-700">API key<Input className="mt-1" type="password" autoComplete="new-password" value={form.data.api_key} onChange={(e) => form.setData('api_key', e.target.value)} placeholder={editing ? 'Leave blank to keep the current key' : definition?.requires_api_key ? 'Required before enabling' : 'Not required'} /></label>
                        {['openai_compatible', 'ollama'].includes(form.data.provider) && <label className="text-sm font-medium text-slate-700 sm:col-span-2">Endpoint URL<Input className="mt-1" type="url" value={form.data.base_url || ''} onChange={(e) => form.setData('base_url', e.target.value)} placeholder={form.data.provider === 'ollama' ? 'http://host:11434/api' : 'https://provider.example/v1'} required /></label>}
                        <label className="text-sm font-medium text-slate-700">Temperature<Input className="mt-1" type="number" min="0" max="1.5" step="0.1" value={form.data.temperature} onChange={(e) => form.setData('temperature', e.target.value)} /></label>
                        <label className="text-sm font-medium text-slate-700">Maximum output tokens<Input className="mt-1" type="number" min="64" max="8192" value={form.data.max_tokens} onChange={(e) => form.setData('max_tokens', e.target.value)} /></label>
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold text-slate-800">Verified pricing for the default chat model</h3>
                        <p className="mt-1 text-xs text-slate-500">Enter the rates from your provider contract, per one million tokens. Leave input or output blank when pricing is unknown; PromptBot will not estimate a cost.</p>
                        <div className="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            <label className="text-sm font-medium text-slate-700">Currency<Input className="mt-1 uppercase" maxLength={3} value={form.data.pricing_currency} onChange={(e) => form.setData('pricing_currency', e.target.value.toUpperCase())} /></label>
                            <label className="text-sm font-medium text-slate-700">Input<Input className="mt-1" type="number" min="0" step="0.000001" value={form.data.input_cost_per_million} onChange={(e) => form.setData('input_cost_per_million', e.target.value)} /></label>
                            <label className="text-sm font-medium text-slate-700">Output<Input className="mt-1" type="number" min="0" step="0.000001" value={form.data.output_cost_per_million} onChange={(e) => form.setData('output_cost_per_million', e.target.value)} /></label>
                            <label className="text-sm font-medium text-slate-700">Cached input<Input className="mt-1" type="number" min="0" step="0.000001" value={form.data.cached_input_cost_per_million} onChange={(e) => form.setData('cached_input_cost_per_million', e.target.value)} /></label>
                            <label className="text-sm font-medium text-slate-700">Reasoning output<Input className="mt-1" type="number" min="0" step="0.000001" value={form.data.reasoning_cost_per_million} onChange={(e) => form.setData('reasoning_cost_per_million', e.target.value)} /></label>
                        </div>
                    </div>
                    {Object.keys(form.errors).length > 0 && <p className="text-sm text-rose-600">{Object.values(form.errors)[0]}</p>}
                    <Switch checked={form.data.enabled} onChange={(value) => form.setData('enabled', value)} label="Enabled" description="Disabled providers remain configured but cannot serve agent runs." />
                    <div className="flex gap-2"><Button type="submit" loading={form.processing}>{editing ? 'Save provider' : 'Add provider'}</Button>{editing && <Button variant="ghost" onClick={reset}>Cancel</Button>}</div>
                </form>
            </SectionCard>}

            <div className={`${canManage ? 'mt-6' : ''} space-y-4`}>
                {providers.map((provider) => <SectionCard key={provider.public_uuid} title={provider.name} description={`${provider.provider.replaceAll('_', ' ')} · ${provider.default_chat_model}`} actions={<Badge tone={provider.status === 'healthy' ? 'success' : provider.status === 'authentication_failed' ? 'danger' : 'neutral'}>{provider.status.replaceAll('_', ' ')}</Badge>}>
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-500">
                            <span className="inline-flex items-center gap-1.5"><KeyRound className="h-4 w-4" />{provider.credential_configured ? 'Credential configured' : 'Credential required'}</span>
                            {provider.last_test_status === 'passed' && <span className="inline-flex items-center gap-1.5 text-emerald-700"><CheckCircle2 className="h-4 w-4" />Connection verified</span>}
                            {provider.last_error_message && <span className="text-rose-600">{provider.last_error_message}</span>}
                        </div>
                        <div className="flex gap-2">
                            <Button size="sm" variant="secondary" icon={RefreshCw} onClick={() => router.post(route('tenant.admin.ai.providers.test', provider.public_uuid), {}, { preserveScroll: true })}>Test</Button>
                            {canManage && <Button size="sm" variant="ghost" onClick={() => edit(provider)}>Edit</Button>}
                            {canManage && <Button size="sm" variant="ghost" icon={Trash2} onClick={() => window.confirm(`Delete ${provider.name}?`) && router.delete(route('tenant.admin.ai.providers.destroy', provider.public_uuid), { preserveScroll: true })}>Delete</Button>}
                        </div>
                    </div>
                    <div className="mt-4 flex flex-wrap gap-2">{provider.capabilities.map((capability) => <Badge key={capability} tone="neutral">{capability.replaceAll('_', ' ')}</Badge>)}</div>
                </SectionCard>)}
                {!providers.length && <div className="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center"><p className="font-semibold text-slate-800">No providers configured</p><p className="mt-1 text-sm text-slate-500">Add a provider above. No requests are made until you explicitly test or run it.</p></div>}
            </div>
        </AIShell>
    );
}
