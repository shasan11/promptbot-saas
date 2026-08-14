import PageHeader from '@/Components/Superadmin/PageHeader';
import { Card } from '@/Components/UI/Card';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, X } from 'lucide-react';
import { useState } from 'react';

function PurposeCard({ purpose, availableModels }) {
    const [selected, setSelected] = useState('');
    const candidates = availableModels.filter((model) => model.capability === purpose.capability);

    const addModel = () => {
        if (!selected) return;
        router.post(route('superadmin.ai.assignments.store'), { purpose: purpose.purpose, ai_model_id: selected }, { preserveScroll: true });
        setSelected('');
    };

    const removeAssignment = (assignment) => router.delete(route('superadmin.ai.assignments.destroy', assignment.id), { preserveScroll: true });

    const reorder = (assignment, direction) => {
        const newPriority = Math.max(0, assignment.priority + direction * 10);
        router.put(route('superadmin.ai.assignments.update', assignment.id), { priority: newPriority }, { preserveScroll: true });
    };

    return (
        <Card>
            <div className="mb-3">
                <h3 className="text-sm font-bold text-slate-900">{purpose.label}</h3>
                <p className="text-xs text-slate-500">Capability: {purpose.capability}. Tried in order — the first available model wins, the rest are fallback.</p>
            </div>

            <ol className="space-y-2">
                {purpose.assignments.length === 0 && (
                    <li className="rounded-md border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500">
                        No model assigned — falls back to the provider default for this capability, or fails with a configuration-missing error.
                    </li>
                )}
                {purpose.assignments.map((assignment, index) => (
                    <li key={assignment.id} className="flex items-center justify-between gap-3 rounded-md border border-slate-200 px-3 py-2">
                        <div>
                            <div className="text-sm font-semibold text-slate-900">{assignment.model.display_name}</div>
                            <div className="text-xs text-slate-500">{assignment.model.provider_name} &middot; {assignment.model.model_key}</div>
                        </div>
                        <div className="flex items-center gap-1">
                            <button type="button" disabled={index === 0} onClick={() => reorder(assignment, -1)} className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"><ArrowUp className="h-3.5 w-3.5" /></button>
                            <button type="button" disabled={index === purpose.assignments.length - 1} onClick={() => reorder(assignment, 1)} className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"><ArrowDown className="h-3.5 w-3.5" /></button>
                            <button type="button" onClick={() => removeAssignment(assignment)} className="rounded p-1 text-rose-500 hover:bg-rose-50"><X className="h-3.5 w-3.5" /></button>
                        </div>
                    </li>
                ))}
            </ol>

            <div className="mt-4 flex gap-2">
                <Select value={selected} onChange={(e) => setSelected(e.target.value)} className="flex-1">
                    <option value="">Add a model to this chain…</option>
                    {candidates.map((model) => <option key={model.id} value={model.id}>{model.display_name} ({model.provider_name})</option>)}
                </Select>
                <Button type="button" variant="secondary" onClick={addModel} disabled={!selected}>Add</Button>
            </div>
        </Card>
    );
}

export default function Index({ purposes, availableModels }) {
    return (
        <AuthenticatedLayout
            header={<PageHeader title="Model Assignments" subtitle="Select which models PromptBot uses for each AI purpose, with fallback ordering." />}
        >
            <Head title="Model Assignments" />

            <div className="grid gap-4 sm:grid-cols-2">
                {purposes.map((purpose) => (
                    <PurposeCard key={purpose.purpose} purpose={purpose} availableModels={availableModels} />
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
