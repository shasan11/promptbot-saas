<?php

namespace App\Services\Installer;

use App\Models\CentralUser;
use App\Services\Tenancy\CpanelDatabaseProvisioner;
use App\Services\Tenancy\ManualDatabaseProvisioner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TenancyInstallationService
{
    public function requirements(): array
    {
        return collect(config('installer.requirements', []))->mapWithKeys(fn (string $extension) => [
            $extension => extension_loaded($extension),
        ])->all();
    }

    public function permissions(): array
    {
        return collect(config('installer.permissions', []))->mapWithKeys(fn (string $mode, string $path) => [
            $path => is_writable(base_path($path)) || ($path === '.env' && ! file_exists(base_path('.env')) && is_writable(base_path())),
        ])->all();
    }

    public function testCentralDatabase(array $credentials): bool
    {
        config()->set('database.connections.install_verify', array_merge(config('database.connections.mysql'), [
            'host' => $credentials['host'] ?? '127.0.0.1',
            'port' => $credentials['port'] ?? 3306,
            'database' => $credentials['database'] ?? '',
            'username' => $credentials['username'] ?? '',
            'password' => $credentials['password'] ?? '',
        ]));

        try {
            DB::connection('install_verify')->getPdo();

            return true;
        } finally {
            DB::purge('install_verify');
        }
    }

    public function testTenantProvisioning(array $data): bool
    {
        return match ($data['mode'] ?? config('saas.db_provisioning_mode')) {
            'manual' => app(ManualDatabaseProvisioner::class)->verifyDatabaseAccess($data),
            'cpanel' => app(CpanelDatabaseProvisioner::class)->testConnection(),
            'mysql' => true,
            default => false,
        };
    }

    public function finalize(array $centralAdmin): void
    {
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

        if (! CentralUser::where('email', $centralAdmin['email'])->exists()) {
            CentralUser::create($centralAdmin);
        }

        Artisan::call('optimize:clear');
        File::put(storage_path('installed'), now()->toIso8601String());
    }

    public function installed(): bool
    {
        return file_exists(storage_path('installed'));
    }
}
