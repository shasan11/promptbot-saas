<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantBackupCommand extends Command
{
    protected $signature = 'tenant:backup {tenant}';

    protected $description = 'Display safe backup instructions for a tenant database.';

    public function handle(): int
    {
        $tenant = Tenant::findOrFail($this->argument('tenant'));
        $this->warn('Automatic database dumps are host-specific and are not executed from HTTP requests.');
        $this->line('Tenant database: '.$tenant->tenancy_db_name);
        $this->line('Use cPanel Backup, phpMyAdmin export, or a host-approved scheduled mysqldump for this database.');

        return self::SUCCESS;
    }
}
