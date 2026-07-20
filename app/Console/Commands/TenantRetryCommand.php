<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Console\Command;

class TenantRetryCommand extends Command
{
    protected $signature = 'tenant:retry {tenant}';

    protected $description = 'Retry tenant provisioning.';

    public function handle(TenantProvisioningService $provisioning): int
    {
        $tenant = Tenant::findOrFail($this->argument('tenant'));
        $provisioning->retry($tenant);
        $this->info('Provisioning retry completed.');

        return self::SUCCESS;
    }
}
