<?php

namespace App\Console\Commands;

use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Console\Command;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create
        {--company= : Company name}
        {--slug= : Tenant slug}
        {--owner-name= : Owner name}
        {--owner-email= : Owner email}
        {--owner-password= : Owner password}
        {--plan= : Plan id}
        {--mode= : manual, cpanel or mysql}
        {--db-host=127.0.0.1}
        {--db-port=3306}
        {--db-name=}
        {--db-username=}
        {--db-password=}';

    protected $description = 'Create and provision a tenant.';

    public function handle(TenantProvisioningService $provisioning): int
    {
        $tenant = $provisioning->provision([
            'company_name' => $this->option('company') ?? $this->ask('Company name'),
            'slug' => $this->option('slug'),
            'owner_name' => $this->option('owner-name') ?? $this->ask('Owner name'),
            'owner_email' => $this->option('owner-email') ?? $this->ask('Owner email'),
            'owner_password' => $this->option('owner-password') ?? $this->secret('Owner password'),
            'plan_id' => $this->option('plan'),
            'provisioning_mode' => $this->option('mode') ?? config('saas.db_provisioning_mode'),
            'database_host' => $this->option('db-host'),
            'database_port' => $this->option('db-port'),
            'database_name' => $this->option('db-name'),
            'database_username' => $this->option('db-username'),
            'database_password' => $this->option('db-password'),
        ]);

        $this->info("Tenant {$tenant->id} is {$tenant->status->value}.");

        return self::SUCCESS;
    }
}
