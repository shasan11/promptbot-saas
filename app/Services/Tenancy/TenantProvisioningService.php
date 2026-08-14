<?php

namespace App\Services\Tenancy;

use App\Enums\Tenant\UserStatus;
use App\Enums\TenantStatus;
use App\Exceptions\TenancyProvisioningException;
use App\Jobs\Tenancy\ProvisionTenantJob;
use App\Models\Plan;
use App\Models\ProvisioningLog;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\DefaultCustomerAccountService;
use App\Services\Platform\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class TenantProvisioningService
{
    public function __construct(
        private readonly TenantDatabaseProvisionerFactory $provisioners,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function provision(array $data): Tenant
    {
        $data = $this->validateProvisioningData($data);
        $data['customer_account_id'] ??= app(DefaultCustomerAccountService::class)->get()->getKey();
        $slug = Str::slug($data['slug'] ?? $data['company_name']);
        $lock = Cache::lock('tenant-provision:'.$slug, (int) config('saas.locks.provisioning_ttl', 600));

        if (! $lock->get()) {
            throw new TenancyProvisioningException('A provisioning request for this tenant is already running.', 'lock');
        }

        try {
            $tenant = Tenant::firstOrCreate(
                ['slug' => $slug],
                [
                    'id' => $slug,
                    'customer_account_id' => $data['customer_account_id'] ?? null,
                    'company_name' => $data['company_name'],
                    'region' => $data['region'] ?? null,
                    'plan_id' => $data['plan_id'] ?? Plan::query()->where('is_active', true)->orderBy('sort_order')->value('id'),
                    'status' => TenantStatus::Pending,
                ]
            );

            if (isset($data['customer_account_id']) && (int) $tenant->customer_account_id !== (int) $data['customer_account_id']) {
                throw new TenancyProvisioningException('This workspace slug is already owned by another customer account.', 'validation');
            }

            return $this->runProvisioning($tenant, $data);
        } finally {
            optional($lock)->release();
        }
    }

    public function queueProvisioning(array $data): Tenant
    {
        $data = $this->validateProvisioningData($data);
        $data['customer_account_id'] ??= app(DefaultCustomerAccountService::class)->get()->getKey();
        $slug = Str::slug($data['slug'] ?? $data['company_name']);

        $tenant = Tenant::firstOrCreate(
            ['slug' => $slug],
            [
                'id' => $slug,
                'customer_account_id' => $data['customer_account_id'],
                'company_name' => $data['company_name'],
                'region' => $data['region'] ?? null,
                'plan_id' => $data['plan_id'] ?? Plan::query()->where('is_active', true)->orderBy('sort_order')->value('id'),
                'status' => TenantStatus::Pending,
                'provisioning_step' => 'queued',
            ]
        );

        if ((int) $tenant->customer_account_id !== (int) $data['customer_account_id']) {
            throw new TenancyProvisioningException('This workspace slug is already owned by another customer account.', 'validation');
        }

        ProvisionTenantJob::dispatch($data);

        return $tenant->refresh();
    }

    public function retry(Tenant $tenant, array $data = []): Tenant
    {
        return $this->runProvisioning($tenant, [...[
            'company_name' => $tenant->company_name,
            'slug' => $tenant->slug,
            'region' => $tenant->region,
            'create_tenant_owner' => false,
            'provisioning_mode' => config('saas.db_provisioning_mode', 'manual'),
        ], ...$data]);
    }

    public function suspend(Tenant $tenant): void
    {
        $tenant->forceFill([
            'status' => TenantStatus::Suspended,
            'suspended_at' => now(),
        ])->save();
    }

    public function activate(Tenant $tenant): void
    {
        $tenant->forceFill([
            'status' => TenantStatus::Active,
            'suspended_at' => null,
        ])->save();
    }

    public function delete(Tenant $tenant): void
    {
        $tenant->forceFill([
            'status' => TenantStatus::Deleted,
            'deleted_at' => now(),
        ])->save();
    }

    public function verifyDatabaseAccess(array $credentials): bool
    {
        return app(ManualDatabaseProvisioner::class)->verifyDatabaseAccess($credentials);
    }

    protected function runProvisioning(Tenant $tenant, array $data): Tenant
    {
        try {
            $this->mark($tenant, TenantStatus::Provisioning, 'started');

            if (! $tenant->tenancy_db_name) {
                $this->mark($tenant, TenantStatus::DatabaseCreating, 'database_creating');
                $credentials = $this->provisioners->make($data['provisioning_mode'] ?? null)->provision($tenant, $data);
                $tenant->forceFill([
                    'tenancy_db_connection' => 'tenant_template',
                    'tenancy_db_name' => $credentials['database'],
                    'tenancy_db_host' => $credentials['host'] ?? config('database.connections.tenant_template.host'),
                    'tenancy_db_port' => $credentials['port'] ?? config('database.connections.tenant_template.port'),
                    'tenancy_db_username' => $credentials['username'] ?? config('database.connections.tenant_template.username'),
                    'tenancy_db_password' => $credentials['password'] ?? config('database.connections.tenant_template.password'),
                    'database_created_by_app' => (bool) ($credentials['created_by_app'] ?? false),
                ])->save();
                $this->mark($tenant, TenantStatus::DatabaseCreated, 'database_created');
            }

            $this->mark($tenant, TenantStatus::Migrating, 'migrating');
            Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
            $this->log($tenant, 'migrating', 'ok', Artisan::output());

            $this->mark($tenant, TenantStatus::Seeding, 'seeding');
            Artisan::call('tenants:seed', ['--tenants' => [$tenant->id], '--class' => 'Database\\Seeders\\TenantDatabaseSeeder', '--force' => true]);
            if ($data['create_tenant_owner'] ?? true) {
                $this->createTenantOwner($tenant, $data);
            }

            if (! $tenant->domains()->where('type', 'subdomain')->exists()) {
                $tenant->domains()->create([
                    'domain' => $data['subdomain'] ?? $tenant->slug.'.'.config('saas.tenant_base_domain'),
                    'type' => 'subdomain',
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'is_primary' => true,
                ]);
            }

            $this->mark($tenant, TenantStatus::Active, 'active');
            $tenant->forceFill(['provisioned_at' => now(), 'last_provisioning_error' => null])->save();
            $this->subscriptions->createInitialSubscription($tenant, null, $data['billing_interval'] ?? 'monthly');

            return $tenant->refresh();
        } catch (Throwable $exception) {
            $step = $exception instanceof TenancyProvisioningException ? $exception->step : ($tenant->provisioning_step ?: 'unknown');
            $tenant->forceFill([
                'status' => TenantStatus::Failed,
                'provisioning_step' => $step,
                'last_provisioning_error' => $this->sanitize($exception->getMessage()),
            ])->save();
            $this->log($tenant, $step, 'failed', $exception->getMessage());

            throw $exception;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    protected function createTenantOwner(Tenant $tenant, array $data): void
    {
        tenancy()->initialize($tenant);

        try {
            $owner = User::firstOrCreate(
                ['email' => $data['owner_email']],
                [
                    'name' => $data['owner_name'],
                    'password' => Hash::make($data['owner_password']),
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'password_changed_at' => now(),
                ]
            );
            $owner->assignRole('Tenant Owner');
            Setting::firstOrCreate(['key' => 'general.workspace_name'], ['value' => ['value' => $tenant->company_name]]);
        } finally {
            tenancy()->end();
        }
    }

    protected function mark(Tenant $tenant, TenantStatus $status, string $step): void
    {
        $tenant->forceFill(['status' => $status, 'provisioning_step' => $step])->save();
        $this->log($tenant, $step, 'ok', $status->value);
    }

    protected function log(Tenant $tenant, string $step, string $status, ?string $message = null, array $context = []): void
    {
        ProvisioningLog::create([
            'tenant_id' => $tenant->id,
            'step' => $step,
            'status' => $status,
            'message' => $message ? $this->sanitize($message) : null,
            'context' => $this->sanitizeContext($context),
        ]);
    }

    protected function validateProvisioningData(array $data): array
    {
        return Validator::make($data, [
            'company_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:60'],
            'subdomain' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'customer_account_id' => ['nullable', 'exists:customer_accounts,id'],
            'create_tenant_owner' => ['nullable', 'boolean'],
            'owner_name' => ['nullable', 'required_if:create_tenant_owner,true', 'string', 'max:255'],
            'owner_email' => ['nullable', 'required_if:create_tenant_owner,true', 'email:rfc'],
            'owner_password' => ['nullable', 'required_if:create_tenant_owner,true', 'string', 'min:10'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'billing_interval' => ['nullable', 'in:monthly,yearly'],
            'provisioning_mode' => ['nullable', 'in:manual,cpanel,mysql'],
            'database_host' => ['nullable', 'string', 'max:255'],
            'database_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['nullable', 'required_if:provisioning_mode,manual', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'database_username' => ['nullable', 'required_if:provisioning_mode,manual', 'string', 'max:255'],
            'database_password' => ['nullable', 'string'],
        ])->validate();
    }

    protected function sanitize(string $value): string
    {
        return preg_replace('/(password|token|secret|key)=([^\\s&]+)/i', '$1=[redacted]', $value) ?? '[redacted]';
    }

    protected function sanitizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/password|token|secret|key/i', (string) $key)) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }
}
