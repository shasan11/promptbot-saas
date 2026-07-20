<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantsHealthCommand extends Command
{
    protected $signature = 'tenants:health';

    protected $description = 'Run tenant health checks for all tenants.';

    public function handle(): int
    {
        $failed = false;

        Tenant::query()->each(function (Tenant $tenant) use (&$failed): void {
            $this->line("Checking {$tenant->id}");
            $code = Artisan::call('tenant:health', ['tenant' => $tenant->id]);
            $this->line(Artisan::output());
            $failed = $failed || $code !== self::SUCCESS;
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
