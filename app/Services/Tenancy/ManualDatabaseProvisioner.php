<?php

namespace App\Services\Tenancy;

use App\Contracts\TenantDatabaseProvisioner;
use App\Exceptions\TenancyProvisioningException;
use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

class ManualDatabaseProvisioner implements TenantDatabaseProvisioner
{
    public function provision(Tenant $tenant, array $data): array
    {
        $credentials = [
            'host' => (string) ($data['database_host'] ?? $data['host'] ?? '127.0.0.1'),
            'port' => (int) ($data['database_port'] ?? $data['port'] ?? 3306),
            'database' => TenantDatabaseName::assertSafe((string) ($data['database_name'] ?? $data['database'] ?? '')),
            'username' => (string) ($data['database_username'] ?? $data['username'] ?? ''),
            'password' => (string) ($data['database_password'] ?? $data['password'] ?? ''),
            'created_by_app' => false,
        ];

        if (! $this->verifyDatabaseAccess($credentials)) {
            throw new TenancyProvisioningException('The supplied tenant database could not be verified.', 'database_verification');
        }

        return $credentials;
    }

    public function verifyDatabaseAccess(array $credentials): bool
    {
        $name = 'tenant_verify_'.substr(sha1(json_encode($credentials)), 0, 12);

        Config::set("database.connections.$name", [
            'driver' => 'mysql',
            'host' => $credentials['host'] ?? '127.0.0.1',
            'port' => $credentials['port'] ?? 3306,
            'database' => $credentials['database'] ?? '',
            'username' => $credentials['username'] ?? '',
            'password' => $credentials['password'] ?? '',
            'charset' => config('database.connections.mysql.charset', 'utf8mb4'),
            'collation' => config('database.connections.mysql.collation', 'utf8mb4_unicode_ci'),
            'strict' => true,
        ]);

        $table = 'tenant_access_check_'.substr(sha1((string) microtime(true)), 0, 10);

        try {
            DB::connection($name)->getPdo();
            DB::connection($name)->statement("CREATE TABLE `$table` (`id` int unsigned not null primary key)");
            DB::connection($name)->statement("ALTER TABLE `$table` ADD COLUMN `checked_at` timestamp null");
            DB::connection($name)->statement("DROP TABLE `$table`");

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            try {
                DB::connection($name)->statement("DROP TABLE IF EXISTS `$table`");
            } catch (Throwable) {
                //
            }

            DB::purge($name);
            Config::set("database.connections.$name", null);
        }
    }
}
