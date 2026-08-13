import PageHeader from '@/Components/Superadmin/PageHeader';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ tenants = [], administrators = [], selectedTenantId = null }) {
    const { data, setData, post, processing, errors } = useForm({
        tenant_id: selectedTenantId || tenants[0]?.id || '',
        subject: '',
        description: '',
        priority: 'normal',
        category: '',
        assigned_to: '',
        requester_name: '',
        requester_email: '',
        sla_due_at: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('superadmin.tickets.store'));
    };

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="Create ticket"
                    subtitle="Open a support case for a tenant and assign its initial priority and SLA."
                    actions={<Button href={route('superadmin.tickets.index')} variant="secondary">Back to tickets</Button>}
                />
            )}
        >
            <Head title="Create ticket" />

            {data.priority === 'urgent' && (
                <Alert tone="warning" title="Urgent tickets page the on-call assignee" className="mb-6">
                    Make sure the assigned administrator and SLA due time reflect the urgency before creating this ticket.
                </Alert>
            )}

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6">
                <SectionCard title="Tenant and case">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="tenant_id" label="Tenant" required error={errors.tenant_id}>
                            <Select id="tenant_id" value={data.tenant_id} error={!!errors.tenant_id} onChange={(event) => setData('tenant_id', event.target.value)}>
                                <option value="">Select tenant</option>
                                {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="assigned_to" label="Assigned administrator" optional error={errors.assigned_to}>
                            <Select id="assigned_to" value={data.assigned_to} onChange={(event) => setData('assigned_to', event.target.value)}>
                                <option value="">Unassigned</option>
                                {administrators.map((admin) => <option key={admin.id} value={admin.id}>{admin.name} · {admin.email}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="subject" label="Subject" required error={errors.subject} className="md:col-span-2">
                            <Input id="subject" value={data.subject} error={!!errors.subject} onChange={(event) => setData('subject', event.target.value)} />
                        </FormField>
                        <FormField id="description" label="Description" required error={errors.description} className="md:col-span-2">
                            <Textarea id="description" rows={7} value={data.description} error={!!errors.description} onChange={(event) => setData('description', event.target.value)} />
                        </FormField>
                        <FormField id="priority" label="Priority" required error={errors.priority}>
                            <Select id="priority" value={data.priority} onChange={(event) => setData('priority', event.target.value)}>
                                {['low', 'normal', 'high', 'urgent'].map((priority) => <option key={priority} value={priority}>{priority}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="category" label="Category" optional error={errors.category}>
                            <Input id="category" value={data.category} onChange={(event) => setData('category', event.target.value)} placeholder="Billing, technical, account…" />
                        </FormField>
                        <FormField id="requester_name" label="Requester name" optional error={errors.requester_name}>
                            <Input id="requester_name" value={data.requester_name} onChange={(event) => setData('requester_name', event.target.value)} />
                        </FormField>
                        <FormField id="requester_email" label="Requester email" optional error={errors.requester_email}>
                            <Input id="requester_email" type="email" value={data.requester_email} onChange={(event) => setData('requester_email', event.target.value)} />
                        </FormField>
                        <FormField id="sla_due_at" label="SLA due" optional error={errors.sla_due_at} className="md:col-span-2">
                            <Input id="sla_due_at" type="datetime-local" value={data.sla_due_at} onChange={(event) => setData('sla_due_at', event.target.value)} />
                        </FormField>
                    </div>
                </SectionCard>

                <div className="flex justify-end gap-3">
                    <Button href={route('superadmin.tickets.index')} variant="secondary">Cancel</Button>
                    <Button type="submit" variant="brand" loading={processing}>Create ticket</Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
