<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantMigrateCommand extends Command
{
    protected $signature = 'tenant:migrate {tenant}';

    protected $description = 'Run tenant migrations for one tenant.';

    public function handle(): int
    {
        $tenant = Tenant::findOrFail($this->argument('tenant'));
        $code = Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
        $this->line(Artisan::output());

        return $code;
    }
}
