<?php

namespace App\Services\Tenancy;

use App\Contracts\TenantDatabaseProvisioner;
use App\Exceptions\TenancyProvisioningException;
use App\Models\Tenant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class CpanelDatabaseProvisioner extends ManualDatabaseProvisioner implements TenantDatabaseProvisioner
{
    public function provision(Tenant $tenant, array $data): array
    {
        $prefix = trim((string) config('saas.cpanel.database_prefix'), '_');
        $database = TenantDatabaseName::fromSlug($tenant->slug, $prefix === '' ? '' : $prefix.'_');
        $user = (string) config('saas.cpanel.database_user');

        if ($user === '') {
            throw new TenancyProvisioningException('CPANEL_DATABASE_USER is required for cPanel provisioning.', 'database_creating');
        }

        $this->createDatabase($database);
        $this->setPrivileges($database, $user);

        return [
            'host' => parse_url((string) config('saas.cpanel.host'), PHP_URL_HOST) ?: (string) config('saas.cpanel.host'),
            'port' => 3306,
            'database' => $database,
            'username' => $user,
            'password' => (string) ($data['database_password'] ?? config('database.connections.tenant_template.password')),
            'created_by_app' => true,
        ];
    }

    public function testConnection(): bool
    {
        $response = $this->client()->get('/execute/Mysql/list_databases');

        return $response->successful();
    }

    protected function createDatabase(string $database): void
    {
        $response = $this->client()->get('/execute/Mysql/create_database', ['name' => $database]);

        if (! $response->successful() || data_get($response->json(), 'status') === 0) {
            throw new TenancyProvisioningException('cPanel could not create the tenant database.', 'database_creating', [
                'cpanel_error' => data_get($response->json(), 'errors.0'),
            ]);
        }
    }

    protected function setPrivileges(string $database, string $user): void
    {
        $response = $this->client()->get('/execute/Mysql/set_privileges_on_database', [
            'user' => $user,
            'database' => $database,
            'privileges' => 'ALL PRIVILEGES',
        ]);

        if (! $response->successful() || data_get($response->json(), 'status') === 0) {
            throw new TenancyProvisioningException('cPanel could not assign database privileges.', 'database_created', [
                'cpanel_error' => data_get($response->json(), 'errors.0'),
            ]);
        }
    }

    protected function client(): PendingRequest
    {
        $host = rtrim((string) config('saas.cpanel.host'), '/');
        $token = (string) config('saas.cpanel.api_token');
        $username = (string) config('saas.cpanel.username');

        if ($host === '' || $token === '' || $username === '') {
            throw new TenancyProvisioningException('cPanel host, username and API token are required.', 'database_creating');
        }

        return Http::baseUrl($host)
            ->withToken($username.':'.$token, 'cpanel')
            ->withOptions(['verify' => (bool) config('saas.cpanel.verify_ssl')]);
    }
}
