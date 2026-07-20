<?php

namespace App\Services\Tenancy;

use App\Contracts\TenantDatabaseProvisioner;
use App\Exceptions\TenancyProvisioningException;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Throwable;

class MysqlAdminDatabaseProvisioner extends ManualDatabaseProvisioner implements TenantDatabaseProvisioner
{
    public function provision(Tenant $tenant, array $data): array
    {
        $database = TenantDatabaseName::fromSlug($tenant->slug, config('tenancy.database.prefix'));

        try {
            DB::connection('tenant_admin')->statement(
                sprintf(
                    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET `%s` COLLATE `%s`',
                    $database,
                    config('database.connections.tenant_template.charset', 'utf8mb4'),
                    config('database.connections.tenant_template.collation', 'utf8mb4_unicode_ci')
                )
            );
        } catch (Throwable $exception) {
            throw new TenancyProvisioningException('Privileged MySQL database creation failed.', 'database_creating', [], $exception);
        }

        return [
            'host' => config('database.connections.tenant_template.host'),
            'port' => (int) config('database.connections.tenant_template.port', 3306),
            'database' => $database,
            'username' => config('database.connections.tenant_template.username'),
            'password' => config('database.connections.tenant_template.password'),
            'created_by_app' => true,
        ];
    }
}
