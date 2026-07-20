<?php

namespace App\Services\Tenancy;

use App\Contracts\TenantDatabaseProvisioner;
use InvalidArgumentException;

class TenantDatabaseProvisionerFactory
{
    public function make(?string $mode = null): TenantDatabaseProvisioner
    {
        return match ($mode ?? config('saas.db_provisioning_mode', 'manual')) {
            'manual' => app(ManualDatabaseProvisioner::class),
            'cpanel' => app(CpanelDatabaseProvisioner::class),
            'mysql' => app(MysqlAdminDatabaseProvisioner::class),
            default => throw new InvalidArgumentException('Unsupported tenant database provisioning mode.'),
        };
    }
}
