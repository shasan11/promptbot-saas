<?php

namespace App\Contracts;

use App\Models\Tenant;

interface TenantDatabaseProvisioner
{
    /**
     * @return array{host?: string, port?: int, database: string, username?: string, password?: string, created_by_app?: bool}
     */
    public function provision(Tenant $tenant, array $data): array;

    public function verifyDatabaseAccess(array $credentials): bool;
}
