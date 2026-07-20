<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Console\Command;

class TenantActivateCommand extends Command
{
    protected $signature = 'tenant:activate {tenant}';

    protected $description = 'Activate a tenant.';

    public function handle(TenantProvisioningService $provisioning): int
    {
        $provisioning->activate(Tenant::findOrFail($this->argument('tenant')));
        $this->info('Tenant activated.');

        return self::SUCCESS;
    }
}
