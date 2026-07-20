<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TenantTestConnectionCommand extends Command
{
    protected $signature = 'tenant:test-connection {tenant}';

    protected $description = 'Test a tenant database connection without printing credentials.';

    public function handle(): int
    {
        $tenant = Tenant::findOrFail($this->argument('tenant'));

        try {
            tenancy()->initialize($tenant);
            DB::connection('tenant')->getPdo();
            $this->info('Tenant database connection succeeded.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Tenant database connection failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}
