import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

export default function Create({ policy }) {
    const editing = Boolean(policy);
    const { data, setData, post, put, processing, errors } = useForm({
        name: policy?.name || '',
        timezone: policy?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone,
        is_default: policy?.is_default || false,
        status: policy?.status || 'active',
        intervals: policy?.intervals?.map((i) => ({ day_of_week: i.day_of_week, starts_at: i.starts_at.slice(0, 5), ends_at: i.ends_at.slice(0, 5) })) || [],
    });

    const addInterval = (day) => setData('intervals', [...data.intervals, { day_of_week: day, starts_at: '09:00', ends_at: '17:00' }]);
    const removeInterval = (index) => setData('intervals', data.intervals.filter((_, i) => i !== index));
    const updateInterval = (index, key, value) => setData('intervals', data.intervals.map((interval, i) => (i === index ? { ...interval, [key]: value } : interval)));

    const submit = (event) => {
        event.preventDefault();
        editing ? put(route('tenant.admin.administration.business-hours.update', policy.id)) : post(route('tenant.admin.administration.business-hours.store'));
    };

    return (
        <AdministrationShell title={editing ? `Edit ${policy.name}` : 'Create business hours policy'} actions={<Button href={route('tenant.admin.administration.business-hours.index')} variant="secondary">Back</Button>}>
            <Head title={editing ? 'Edit business hours' : 'Create business hours'} />

            {errors.intervals && <Alert tone="danger" className="mb-6">{errors.intervals}</Alert>}

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <SectionCard title="Policy details">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="name" label="Name" required error={errors.name}>
                            <Input id="name" value={data.name} error={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                        </FormField>
                        <FormField id="timezone" label="Timezone" required error={errors.timezone}>
                            <Input id="timezone" value={data.timezone} error={!!errors.timezone} onChange={(e) => setData('timezone', e.target.value)} />
                        </FormField>
                        <FormField id="status" label="Status" error={errors.status}>
                            <Select id="status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </Select>
                        </FormField>
                        <div className="flex items-end pb-2">
                            <Switch label="Default policy" description="Used when a team has none assigned" checked={data.is_default} onChange={(value) => setData('is_default', value)} />
                        </div>
                    </div>
                </SectionCard>

                <SectionCard title="Working hours" description="Add one or more intervals per day. Leave a day empty to mark it closed.">
                    <div className="space-y-4">
                        {DAYS.map((dayName, day) => {
                            const dayIntervals = data.intervals.map((interval, index) => ({ ...interval, index })).filter((i) => i.day_of_week === day);
                            return (
                                <div key={day} className="flex flex-wrap items-start gap-3 rounded-md border border-slate-200 p-3">
                                    <span className="w-24 shrink-0 pt-2 text-sm font-medium text-slate-700">{dayName}</span>
                                    <div className="flex-1 space-y-2">
                                        {dayIntervals.map(({ index, starts_at, ends_at }) => (
                                            <div key={index} className="flex items-center gap-2">
                                                <Input type="time" value={starts_at} onChange={(e) => updateInterval(index, 'starts_at', e.target.value)} className="w-32" />
                                                <span className="text-slate-400">–</span>
                                                <Input type="time" value={ends_at} onChange={(e) => updateInterval(index, 'ends_at', e.target.value)} className="w-32" />
                                                <Button type="button" variant="ghost" size="sm" icon={Trash2} onClick={() => removeInterval(index)} aria-label={`Remove interval on ${dayName}`} />
                                            </div>
                                        ))}
                                        {!dayIntervals.length && <p className="text-xs text-slate-400">Closed</p>}
                                        <Button type="button" variant="ghost" size="sm" icon={Plus} onClick={() => addInterval(day)}>Add interval</Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </SectionCard>

                <div className="flex justify-end gap-3">
                    <Button href={route('tenant.admin.administration.business-hours.index')} variant="secondary">Cancel</Button>
                    <Button type="submit" variant="brand" loading={processing}>{editing ? 'Save changes' : 'Create policy'}</Button>
                </div>
            </form>
        </AdministrationShell>
    );
}
