import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import DescriptionList from '@/Components/UI/DescriptionList';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

const STATUSES = ['trial', 'active', 'past_due', 'cancelled', 'expired', 'suspended', 'manual'];
const INTERVALS = ['monthly', 'annual'];
const RISKY_STATUSES = ['suspended', 'cancelled', 'expired'];

const toDateInput = (value) => (value ? String(value).slice(0, 10) : '');

export default function Show({ subscription, plans = [] }) {
    const { data, setData, patch, processing, errors, isDirty } = useForm({
        plan_id: subscription.plan_id || '',
        status: subscription.status || 'active',
        billing_interval: subscription.billing_interval || 'monthly',
        trial_ends_at: toDateInput(subscription.trial_ends_at),
        current_period_starts_at: toDateInput(subscription.current_period_starts_at),
        current_period_ends_at: toDateInput(subscription.current_period_ends_at),
        grace_ends_at: toDateInput(subscription.grace_ends_at),
        cancelled_at: toDateInput(subscription.cancelled_at),
    });
    const [confirmOpen, setConfirmOpen] = useState(false);

    const isRisky = data.status !== subscription.status && RISKY_STATUSES.includes(data.status);
    const planChanged = String(data.plan_id) !== String(subscription.plan_id);

    const performSubmit = () => {
        patch(route('superadmin.subscriptions.update', subscription.public_uuid || subscription.id));
    };

    const submit = (event) => {
        event.preventDefault();
        if (isRisky || planChanged) {
            setConfirmOpen(true);
            return;
        }
        performSubmit();
    };

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="Subscription"
                    subtitle={`${subscription.tenant?.company_name || 'Tenant'} on ${subscription.plan?.name || 'unknown plan'}`}
                    actions={<Button href={route('superadmin.subscriptions.index')} variant="secondary">Back</Button>}
                />
            )}
        >
            <Head title="Subscription" />

            <SectionCard className="mb-6" title="Summary">
                <DescriptionList
                    columns={4}
                    items={[
                        { label: 'Tenant', value: subscription.tenant?.company_name },
                        { label: 'Plan', value: subscription.plan?.name },
                        { label: 'Status', value: <StatusBadge status={subscription.status} /> },
                        { label: 'Identifier', value: <span className="font-mono text-xs">{subscription.public_uuid || subscription.id}</span> },
                    ]}
                />
            </SectionCard>

            <Alert tone="info" className="mb-6">
                Changing the plan or status here affects the tenant's active access immediately — suspending or cancelling revokes access, reactivating restores it.
            </Alert>

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6">
                <SectionCard title="Plan and status" description="Editing controls — changes take effect once saved.">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="plan_id" label="Plan" error={errors.plan_id}>
                            <Select id="plan_id" value={data.plan_id} onChange={(event) => setData('plan_id', event.target.value)}>
                                {plans.map((plan) => <option key={plan.id} value={plan.id}>{plan.name}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="status" label="Status" error={errors.status}>
                            <Select id="status" value={data.status} onChange={(event) => setData('status', event.target.value)}>
                                {STATUSES.map((status) => <option key={status} value={status}>{status.replace('_', ' ')}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="billing_interval" label="Billing interval" error={errors.billing_interval}>
                            <Select id="billing_interval" value={data.billing_interval} onChange={(event) => setData('billing_interval', event.target.value)}>
                                {INTERVALS.map((interval) => <option key={interval} value={interval}>{interval}</option>)}
                            </Select>
                        </FormField>
                    </div>
                </SectionCard>

                <SectionCard title="Lifecycle dates" description="Leave a date blank to clear it.">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="trial_ends_at" label="Trial ends" error={errors.trial_ends_at}>
                            <Input id="trial_ends_at" type="date" value={data.trial_ends_at} onChange={(event) => setData('trial_ends_at', event.target.value)} />
                        </FormField>
                        <FormField id="current_period_starts_at" label="Current period starts" error={errors.current_period_starts_at}>
                            <Input id="current_period_starts_at" type="date" value={data.current_period_starts_at} onChange={(event) => setData('current_period_starts_at', event.target.value)} />
                        </FormField>
                        <FormField id="current_period_ends_at" label="Current period ends" error={errors.current_period_ends_at}>
                            <Input id="current_period_ends_at" type="date" value={data.current_period_ends_at} onChange={(event) => setData('current_period_ends_at', event.target.value)} />
                        </FormField>
                        <FormField id="grace_ends_at" label="Grace period ends" error={errors.grace_ends_at}>
                            <Input id="grace_ends_at" type="date" value={data.grace_ends_at} onChange={(event) => setData('grace_ends_at', event.target.value)} />
                        </FormField>
                        <FormField id="cancelled_at" label="Cancelled at" error={errors.cancelled_at}>
                            <Input id="cancelled_at" type="date" value={data.cancelled_at} onChange={(event) => setData('cancelled_at', event.target.value)} />
                        </FormField>
                    </div>
                </SectionCard>

                <div className={`sticky bottom-0 -mx-4 border-t border-slate-200 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 ${isDirty ? 'bg-white/95' : 'bg-transparent'}`}>
                    <div className="flex items-center justify-end gap-3">
                        {isDirty && <span className="mr-auto text-xs font-medium text-amber-700">Unsaved changes</span>}
                        <Button href={route('superadmin.subscriptions.index')} variant="secondary">Cancel</Button>
                        <Button type="submit" variant="brand" loading={processing} disabled={!isDirty}>Save subscription</Button>
                    </div>
                </div>
            </form>

            <ConfirmDialog
                open={confirmOpen}
                title="Confirm subscription change"
                variant={isRisky ? 'danger' : 'primary'}
                confirmLabel="Confirm and save"
                onCancel={() => setConfirmOpen(false)}
                onConfirm={() => { setConfirmOpen(false); performSubmit(); }}
            >
                {isRisky && <p className="mb-2">Setting status to <strong>{data.status.replace('_', ' ')}</strong> will immediately suspend the tenant's access.</p>}
                {planChanged && <p>The tenant's plan will change from <strong>{subscription.plan?.name}</strong> to the newly selected plan, updating their available limits and features.</p>}
            </ConfirmDialog>
        </AuthenticatedLayout>
    );
}
