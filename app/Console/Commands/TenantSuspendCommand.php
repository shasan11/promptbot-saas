<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Console\Command;

class TenantSuspendCommand extends Command
{
    protected $signature = 'tenant:suspend {tenant}';

    protected $description = 'Suspend a tenant.';

    public function handle(TenantProvisioningService $provisioning): int
    {
        $provisioning->suspend(Tenant::findOrFail($this->argument('tenant')));
        $this->info('Tenant suspended.');

        return self::SUCCESS;
    }
}
