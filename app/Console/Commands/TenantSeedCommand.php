<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantSeedCommand extends Command
{
    protected $signature = 'tenant:seed {tenant} {--class=Database\\Seeders\\TenantDatabaseSeeder}';

    protected $description = 'Run tenant seeders for one tenant.';

    public function handle(): int
    {
        $tenant = Tenant::findOrFail($this->argument('tenant'));
        $code = Artisan::call('tenants:seed', ['--tenants' => [$tenant->id], '--class' => $this->option('class'), '--force' => true]);
        $this->line(Artisan::output());

        return $code;
    }
}
