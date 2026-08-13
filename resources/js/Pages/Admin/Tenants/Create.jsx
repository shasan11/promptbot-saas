import PageHeader from '@/Components/Superadmin/PageHeader';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, Globe2 } from 'lucide-react';

const cleanBaseDomain = (domain = '') => domain
    .trim()
    .toLowerCase()
    .replace(/^https?:\/\//, '')
    .replace(/^www\./, '')
    .replace(/^\./, '')
    .replace(/\/.*$/, '');

const sanitizeSubdomain = (value = '') => value
    .trimStart()
    .toLowerCase()
    .replace(/^https?:\/\//, '')
    .split('.')[0]
    .replace(/[^a-z0-9-]/g, '')
    .replace(/-{2,}/g, '-');

const cleanSubdomain = (value = '') => sanitizeSubdomain(value)
    .replace(/^-+|-+$/g, '');

const getSubdomainPrefix = (domain = '', baseDomain = '') => {
    const normalizedDomain = cleanBaseDomain(domain);

    if (!normalizedDomain) {
        return '';
    }

    if (baseDomain && normalizedDomain.endsWith(`.${baseDomain}`)) {
        return normalizedDomain.slice(0, -(baseDomain.length + 1));
    }

    return normalizedDomain.split('.')[0];
};

export default function Create({
    plans = [],
    accounts = [],
    tenant = null,
    provisioningMode = 'manual',
    tenantBaseDomain = '',
    selectedAccountId = null,
    defaultRegion = '',
}) {
    const editing = Boolean(tenant);
    const baseDomain = cleanBaseDomain(tenantBaseDomain);
    const primaryDomain = tenant?.domains?.find((domain) => domain.is_primary)
        || tenant?.domains?.[0];

    const {
        data,
        setData,
        transform,
        post,
        patch,
        processing,
        errors,
    } = useForm({
        customer_account_id: tenant?.customer_account_id || selectedAccountId || accounts[0]?.id || '',
        company_name: tenant?.company_name || '',
        region: tenant?.region || defaultRegion,
        subdomain: getSubdomainPrefix(primaryDomain?.domain, baseDomain),
        owner_name: '',
        owner_email: '',
        owner_password: '',
        plan_id: tenant?.plan_id || plans[0]?.id || '',
        provisioning_mode: provisioningMode,
        database_host: '127.0.0.1',
        database_port: 3306,
        database_name: '',
        database_username: '',
        database_password: '',
    });

    const subdomainPrefix = cleanSubdomain(data.subdomain);
    const completeDomain = subdomainPrefix && baseDomain
        ? `${subdomainPrefix}.${baseDomain}`
        : subdomainPrefix;

    const domainError = errors.subdomain || errors.slug;
    const errorCount = Object.keys(errors).length;

    const submit = (event) => {
        event.preventDefault();

        transform((formData) => ({
            ...formData,
            // Keep this only while the backend still requires a tenant slug.
            // It is generated automatically and is no longer shown to the user.
            slug: editing ? tenant.slug : subdomainPrefix,
            subdomain: completeDomain,
        }));

        if (editing) {
            patch(route('superadmin.tenants.update', tenant.public_uuid || tenant.id));
            return;
        }

        post(route('superadmin.tenants.store'));
    };

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={editing ? 'Edit tenant' : 'Provision tenant'}
                    subtitle={editing
                        ? 'Update the company, workspace domain, and commercial plan.'
                        : 'Create a tenant workspace, owner account, plan, and database connection.'}
                    actions={(
                        <Button
                            href={route('superadmin.tenants.index')}
                            variant="secondary"
                        >
                            Back to tenants
                        </Button>
                    )}
                />
            )}
        >
            <Head title={editing ? 'Edit tenant' : 'Provision tenant'} />

            {!editing && (
                <Alert
                    tone="warning"
                    title="This creates real infrastructure"
                    className="mb-6"
                >
                    Provisioning creates a database and tenant domain. Review the
                    information carefully before continuing.
                </Alert>
            )}

            {errorCount > 0 && (
                <Alert
                    tone="danger"
                    title={`${errorCount} field${errorCount === 1 ? '' : 's'} need attention`}
                    className="mb-6"
                >
                    Check the highlighted fields below before submitting.
                </Alert>
            )}

            <form onSubmit={submit} className="mx-auto max-w-5xl space-y-6">
                <SectionCard
                    title="Company and workspace"
                    description="Choose the company name and the address users will use to open this workspace."
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="customer_account_id" label="Customer account" required error={errors.customer_account_id} className="md:col-span-2">
                            <Select id="customer_account_id" value={data.customer_account_id} error={!!errors.customer_account_id} onChange={(event) => setData('customer_account_id', event.target.value)}>
                                <option value="">Select a customer account</option>
                                {accounts.map((account) => <option key={account.id} value={account.id}>{account.name} · {account.account_number}</option>)}
                            </Select>
                        </FormField>
                        <FormField
                            id="company_name"
                            label="Company name"
                            required
                            error={errors.company_name}
                        >
                            <Input
                                id="company_name"
                                value={data.company_name}
                                error={!!errors.company_name}
                                placeholder="Acme Corporation"
                                onChange={(event) => setData('company_name', event.target.value)}
                            />
                        </FormField>
                        <FormField
                            id="region"
                            label="Provisioning region"
                            optional
                            error={errors.region}
                            hint="Used for placement and operational reporting."
                        >
                            <Input
                                id="region"
                                value={data.region}
                                error={!!errors.region}
                                placeholder="us-east-1"
                                onChange={(event) => setData('region', event.target.value)}
                            />
                        </FormField>

                        <FormField
                            id="subdomain"
                            label="Workspace domain"
                            required
                            error={domainError}
                            hint="Use lowercase letters, numbers, and hyphens only."
                            className="md:col-span-2"
                        >
                            <div
                                className={`flex min-h-11 overflow-hidden rounded-lg border bg-white transition focus-within:ring-2 focus-within:ring-offset-1 ${
                                    domainError
                                        ? 'border-red-500 focus-within:border-red-500 focus-within:ring-red-200'
                                        : 'border-slate-300 focus-within:border-indigo-500 focus-within:ring-indigo-200'
                                }`}
                            >
                                <div className="flex min-w-0 flex-1 items-center gap-2 px-3">
                                    <Globe2 className="h-4 w-4 shrink-0 text-slate-400" />
                                    <input
                                        id="subdomain"
                                        type="text"
                                        value={data.subdomain}
                                        maxLength={63}
                                        autoCapitalize="none"
                                        autoCorrect="off"
                                        spellCheck={false}
                                        placeholder="acme"
                                        className="min-w-0 flex-1 border-0 bg-transparent py-2.5 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:ring-0"
                                        onChange={(event) => setData(
                                            'subdomain',
                                            sanitizeSubdomain(event.target.value),
                                        )}
                                    />
                                </div>

                                {baseDomain && (
                                    <div className="flex shrink-0 items-center border-l border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-600">
                                        .{baseDomain}
                                    </div>
                                )}
                            </div>

                            <div className="mt-3 flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                                <div className="rounded-md bg-white p-2 shadow-sm ring-1 ring-slate-200">
                                    <Globe2 className="h-4 w-4 text-indigo-600" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                        Workspace URL
                                    </p>
                                    <p className="mt-1 break-all text-sm font-semibold text-slate-900">
                                        {completeDomain || `workspace.${baseDomain || 'example.com'}`}
                                    </p>
                                </div>
                            </div>
                        </FormField>
                    </div>
                </SectionCard>

                {!editing && (
                    <SectionCard
                        title="Owner account"
                        description="Create the first administrator who will manage this tenant."
                    >
                        <div className="grid gap-5 md:grid-cols-2">
                            <FormField
                                id="owner_name"
                                label="Owner name"
                                required
                                error={errors.owner_name}
                            >
                                <Input
                                    id="owner_name"
                                    value={data.owner_name}
                                    error={!!errors.owner_name}
                                    placeholder="Full name"
                                    onChange={(event) => setData('owner_name', event.target.value)}
                                />
                            </FormField>

                            <FormField
                                id="owner_email"
                                label="Owner email"
                                required
                                error={errors.owner_email}
                            >
                                <Input
                                    id="owner_email"
                                    type="email"
                                    value={data.owner_email}
                                    error={!!errors.owner_email}
                                    placeholder="owner@company.com"
                                    onChange={(event) => setData('owner_email', event.target.value)}
                                />
                            </FormField>

                            <FormField
                                id="owner_password"
                                label="Temporary password"
                                required
                                error={errors.owner_password}
                                hint="Minimum 10 characters. Share it securely; it will not be shown again."
                                className="md:col-span-2"
                            >
                                <Input
                                    id="owner_password"
                                    type="password"
                                    value={data.owner_password}
                                    error={!!errors.owner_password}
                                    autoComplete="new-password"
                                    onChange={(event) => setData('owner_password', event.target.value)}
                                />
                            </FormField>
                        </div>
                    </SectionCard>
                )}

                <SectionCard
                    title="Subscription plan"
                    description="Select the commercial package assigned to this tenant."
                >
                    <FormField
                        id="plan_id"
                        label="Plan"
                        required
                        error={errors.plan_id}
                        className="md:max-w-sm"
                    >
                        <Select
                            id="plan_id"
                            value={data.plan_id}
                            error={!!errors.plan_id}
                            onChange={(event) => setData('plan_id', event.target.value)}
                        >
                            <option value="">Select a plan</option>
                            {plans.map((plan) => (
                                <option key={plan.id} value={plan.id}>
                                    {plan.name}
                                </option>
                            ))}
                        </Select>
                    </FormField>
                </SectionCard>

                {!editing && (
                    <SectionCard
                        title="Database provisioning"
                        description="Configure how the tenant database should be connected or created."
                    >
                        <FormField
                            id="provisioning_mode"
                            label="Provisioning mode"
                            error={errors.provisioning_mode}
                            className="md:max-w-sm"
                        >
                            <Select
                                id="provisioning_mode"
                                value={data.provisioning_mode}
                                error={!!errors.provisioning_mode}
                                onChange={(event) => setData('provisioning_mode', event.target.value)}
                            >
                                <option value="manual">Existing database credentials</option>
                                <option value="mysql">Create with MySQL administrator</option>
                                <option value="cpanel">Create through cPanel</option>
                            </Select>
                        </FormField>

                        {data.provisioning_mode === 'manual' && (
                            <div className="mt-5 grid gap-5 md:grid-cols-2">
                                <FormField
                                    id="database_host"
                                    label="Host"
                                    error={errors.database_host}
                                >
                                    <Input
                                        id="database_host"
                                        value={data.database_host}
                                        error={!!errors.database_host}
                                        onChange={(event) => setData('database_host', event.target.value)}
                                    />
                                </FormField>

                                <FormField
                                    id="database_port"
                                    label="Port"
                                    error={errors.database_port}
                                >
                                    <Input
                                        id="database_port"
                                        type="number"
                                        value={data.database_port}
                                        error={!!errors.database_port}
                                        onChange={(event) => setData('database_port', event.target.value)}
                                    />
                                </FormField>

                                <FormField
                                    id="database_name"
                                    label="Database name"
                                    error={errors.database_name}
                                >
                                    <Input
                                        id="database_name"
                                        value={data.database_name}
                                        error={!!errors.database_name}
                                        onChange={(event) => setData('database_name', event.target.value)}
                                    />
                                </FormField>

                                <FormField
                                    id="database_username"
                                    label="Database username"
                                    error={errors.database_username}
                                >
                                    <Input
                                        id="database_username"
                                        value={data.database_username}
                                        error={!!errors.database_username}
                                        onChange={(event) => setData('database_username', event.target.value)}
                                    />
                                </FormField>

                                <FormField
                                    id="database_password"
                                    label="Database password"
                                    error={errors.database_password}
                                    hint="Leave empty only when the database user allows passwordless access."
                                    className="md:col-span-2"
                                >
                                    <Input
                                        id="database_password"
                                        type="password"
                                        value={data.database_password}
                                        error={!!errors.database_password}
                                        autoComplete="new-password"
                                        onChange={(event) => setData('database_password', event.target.value)}
                                    />
                                </FormField>
                            </div>
                        )}

                        {data.provisioning_mode !== 'manual' && (
                            <p className="mt-4 flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                The database will be created automatically using the
                                platform&apos;s {data.provisioning_mode === 'mysql'
                                    ? 'MySQL administrator credentials'
                                    : 'cPanel integration'}.
                            </p>
                        )}
                    </SectionCard>
                )}

                <div className="sticky bottom-0 left-0 right-0 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6">
                    <div className="mx-auto flex max-w-5xl justify-end gap-3">
                        <Button
                            href={route('superadmin.tenants.index')}
                            variant="secondary"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" variant="brand" loading={processing}>
                            {editing ? 'Save changes' : 'Provision tenant'}
                        </Button>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
