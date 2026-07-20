<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TenantHealthCommand extends Command
{
    protected $signature = 'tenant:health {tenant}';

    protected $description = 'Run sanitized tenant health checks.';

    public function handle(): int
    {
        $tenant = Tenant::with('domains')->findOrFail($this->argument('tenant'));
        $checks = [
            ['central_record', true],
            ['domain_record', $tenant->domains->isNotEmpty()],
            ['provisioning_status', (string) ($tenant->status?->value ?? $tenant->status)],
            ['subscription_status', optional($tenant->subscriptions()->latest()->first())->status?->value ?? 'none'],
        ];

        try {
            tenancy()->initialize($tenant);
            $checks[] = ['database_connection', (bool) DB::connection('tenant')->getPdo()];
            $checks[] = ['tenant_user_table', Schema::connection('tenant')->hasTable('users')];
        } catch (Throwable) {
            $checks[] = ['database_connection', false];
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        $this->table(['Check', 'Result'], $checks);

        return collect($checks)->contains(fn ($check) => $check[1] === false) ? self::FAILURE : self::SUCCESS;
    }
}
