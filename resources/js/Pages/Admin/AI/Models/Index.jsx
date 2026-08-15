import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

function AddModelModal({ open, onClose, providers }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        ai_provider_id: providers[0]?.id || '',
        model_key: '',
        display_name: '',
        capability: 'chat',
        embedding_dimensions: '',
        input_cost_per_million_tokens: '',
        output_cost_per_million_tokens: '',
    });

    const close = () => { reset(); onClose(); };

    const submit = (event) => {
        event.preventDefault();
        post(route('superadmin.ai.models.store'), {
            preserveScroll: true,
            onSuccess: close,
        });
    };

    return (
        <Modal open={open} onClose={close} title="Add model" size="lg">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label="Provider" required error={errors.ai_provider_id}>
                        <Select value={data.ai_provider_id} onChange={(e) => setData('ai_provider_id', e.target.value)}>
                            {providers.map((provider) => <option key={provider.id} value={provider.id}>{provider.name}</option>)}
                        </Select>
                    </FormField>
                    <FormField label="Capability" required>
                        <Select value={data.capability} onChange={(e) => setData('capability', e.target.value)}>
                            <option value="chat">Chat</option>
                            <option value="embedding">Embedding</option>
                        </Select>
                    </FormField>
                    <FormField label="Model ID" required error={errors.model_key} hint="The literal string sent to the provider API.">
                        <Input value={data.model_key} onChange={(e) => setData('model_key', e.target.value)} placeholder="e.g. gpt-4o-mini" />
                    </FormField>
                    <FormField label="Display name" required error={errors.display_name}>
                        <Input value={data.display_name} onChange={(e) => setData('display_name', e.target.value)} />
                    </FormField>
                    {data.capability === 'embedding' && (
                        <FormField label="Embedding dimensions" required error={errors.embedding_dimensions}>
                            <Input type="number" value={data.embedding_dimensions} onChange={(e) => setData('embedding_dimensions', e.target.value)} />
                        </FormField>
                    )}
                    <FormField label="Input cost / 1M tokens (USD)" optional error={errors.input_cost_per_million_tokens}>
                        <Input type="number" step="0.01" value={data.input_cost_per_million_tokens} onChange={(e) => setData('input_cost_per_million_tokens', e.target.value)} />
                    </FormField>
                    <FormField label="Output cost / 1M tokens (USD)" optional error={errors.output_cost_per_million_tokens}>
                        <Input type="number" step="0.01" value={data.output_cost_per_million_tokens} onChange={(e) => setData('output_cost_per_million_tokens', e.target.value)} />
                    </FormField>
                </div>
                <div className="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="secondary" onClick={close}>Cancel</Button>
                    <Button type="submit" variant="brand" loading={processing}>Add model</Button>
                </div>
            </form>
        </Modal>
    );
}

function EditModelModal({ open, onClose, model }) {
    const isEmbedding = model?.capability === 'embedding';

    const { data, setData, put, processing, errors, reset } = useForm({
        display_name: model?.display_name || '',
        context_window: model?.context_window ?? '',
        max_output_tokens: model?.max_output_tokens ?? '',
        embedding_dimensions: model?.embedding_dimensions ?? '',
        input_cost_per_million_tokens: model?.input_cost_per_million_tokens ?? '',
        output_cost_per_million_tokens: model?.output_cost_per_million_tokens ?? '',
        supports_streaming: model?.supports_streaming ?? false,
        supports_json_mode: model?.supports_json_mode ?? false,
    });

    const close = () => { reset(); onClose(); };

    const submit = (event) => {
        event.preventDefault();
        put(route('superadmin.ai.models.update', model.id), {
            preserveScroll: true,
            onSuccess: close,
        });
    };

    if (!model) return null;

    return (
        <Modal open={open} onClose={close} title={`Edit ${model.display_name}`} description={`${model.model_key} · ${isEmbedding ? 'Embedding' : 'Chat'} model`} size="lg">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label="Display name" required error={errors.display_name}>
                        <Input value={data.display_name} onChange={(e) => setData('display_name', e.target.value)} />
                    </FormField>
                    {isEmbedding ? (
                        <FormField label="Embedding dimensions" required error={errors.embedding_dimensions}>
                            <Input type="number" value={data.embedding_dimensions} onChange={(e) => setData('embedding_dimensions', e.target.value)} />
                        </FormField>
                    ) : (
                        <>
                            <FormField label="Context window (tokens)" optional error={errors.context_window}>
                                <Input type="number" value={data.context_window} onChange={(e) => setData('context_window', e.target.value)} />
                            </FormField>
                            <FormField label="Max output tokens" optional error={errors.max_output_tokens}>
                                <Input type="number" value={data.max_output_tokens} onChange={(e) => setData('max_output_tokens', e.target.value)} />
                            </FormField>
                        </>
                    )}
                    <FormField label="Input cost / 1M tokens (USD)" optional error={errors.input_cost_per_million_tokens}>
                        <Input type="number" step="0.01" value={data.input_cost_per_million_tokens} onChange={(e) => setData('input_cost_per_million_tokens', e.target.value)} />
                    </FormField>
                    <FormField label="Output cost / 1M tokens (USD)" optional error={errors.output_cost_per_million_tokens}>
                        <Input type="number" step="0.01" value={data.output_cost_per_million_tokens} onChange={(e) => setData('output_cost_per_million_tokens', e.target.value)} />
                    </FormField>
                    {!isEmbedding && (
                        <>
                            <FormField label="Supports streaming">
                                <div className="mt-2"><Switch checked={data.supports_streaming} onChange={(value) => setData('supports_streaming', value)} /></div>
                            </FormField>
                            <FormField label="Supports JSON mode">
                                <div className="mt-2"><Switch checked={data.supports_json_mode} onChange={(value) => setData('supports_json_mode', value)} /></div>
                            </FormField>
                        </>
                    )}
                </div>
                <div className="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="secondary" onClick={close}>Cancel</Button>
                    <Button type="submit" variant="brand" loading={processing}>Save changes</Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Index({ providers }) {
    const [addOpen, setAddOpen] = useState(false);
    const [editModel, setEditModel] = useState(null);

    const toggle = (model) => router.post(route('superadmin.ai.models.toggle', model.id), {}, { preserveScroll: true });
    const remove = (model) => {
        if (confirm(`Remove model "${model.display_name}"?`)) {
            router.delete(route('superadmin.ai.models.destroy', model.id), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="AI Models"
                    subtitle="Register the models available from each provider. Pricing is manually configured since providers don't always expose it."
                    actions={(
                        <>
                            <Link href={route('superadmin.ai.assignments.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Model assignments</Link>
                            <Button icon={Plus} onClick={() => setAddOpen(true)} disabled={providers.length === 0}>Add model</Button>
                        </>
                    )}
                />
            }
        >
            <Head title="AI Models" />

            <div className="space-y-6">
                {providers.length === 0 && (
                    <Card><p className="text-sm text-slate-500">Add a provider first, then register its models here.</p></Card>
                )}

                {providers.map((provider) => (
                    <Card key={provider.id} padded={false}>
                        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div>
                                <h2 className="text-sm font-bold text-slate-900">{provider.name}</h2>
                                <p className="text-xs text-slate-500">{provider.driver_label}</p>
                            </div>
                            <StatusBadge status={provider.is_enabled ? 'active' : 'cancelled'} />
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Model</th>
                                        <th className="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Type</th>
                                        <th className="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Cost / 1M tokens</th>
                                        <th className="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Enabled</th>
                                        <th className="px-4 py-2.5" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {provider.models.length === 0 && (
                                        <tr><td colSpan={5} className="px-4 py-6 text-center text-sm text-slate-500">No models registered.</td></tr>
                                    )}
                                    {provider.models.map((model) => (
                                        <tr key={model.id}>
                                            <td className="px-4 py-3">
                                                <button type="button" onClick={() => setEditModel(model)} className="font-semibold text-slate-900 hover:text-blue-700">{model.display_name}</button>
                                                <div className="font-mono text-xs text-slate-500">{model.model_key}</div>
                                            </td>
                                            <td className="px-4 py-3"><StatusBadge status={model.capability} /></td>
                                            <td className="px-4 py-3 text-xs text-slate-600">
                                                {model.input_cost_per_million_tokens > 0 || model.output_cost_per_million_tokens > 0
                                                    ? `$${model.input_cost_per_million_tokens} in / $${model.output_cost_per_million_tokens} out`
                                                    : 'Not set'}
                                            </td>
                                            <td className="px-4 py-3"><Switch checked={model.is_enabled} onChange={() => toggle(model)} /></td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-3">
                                                    <button type="button" onClick={() => setEditModel(model)} className="text-xs font-semibold text-blue-700 hover:underline">Edit</button>
                                                    <button type="button" onClick={() => remove(model)} className="text-xs font-semibold text-rose-600 hover:underline">Remove</button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                ))}
            </div>

            <AddModelModal open={addOpen} onClose={() => setAddOpen(false)} providers={providers} />
            {editModel && <EditModelModal open={!!editModel} onClose={() => setEditModel(null)} model={editModel} />}
        </AuthenticatedLayout>
    );
}
