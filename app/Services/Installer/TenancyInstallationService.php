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

        CentralUser::updateOrCreate(['email' => $centralAdmin['email']], $centralAdmin);
        foreach (['Database\\Seeders\\PlatformAuthorizationSeeder','Database\\Seeders\\FeatureSeeder','Database\\Seeders\\PlanSeeder'] as $seeder) {
            Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        Artisan::call('optimize:clear');
        File::put(storage_path('installed'), now()->toIso8601String());
    }

    public function install(array $data): void
    {
        $values = [
            'APP_NAME'=>$data['app_name'], 'APP_ENV'=>'production', 'APP_KEY'=>'base64:'.base64_encode(random_bytes(32)), 'APP_DEBUG'=>'false', 'APP_URL'=>$data['app_url'],
            'CENTRAL_DOMAINS'=>$data['central_domains'], 'TENANT_BASE_DOMAIN'=>$data['tenant_base_domain'], 'DB_CONNECTION'=>'central', 'DB_DRIVER'=>'mysql',
            'DB_HOST'=>$data['db_host'], 'DB_PORT'=>(string)$data['db_port'], 'DB_DATABASE'=>$data['db_database'], 'DB_USERNAME'=>$data['db_username'], 'DB_PASSWORD'=>$data['db_password']??'',
            'TENANT_DB_PROVISIONING_MODE'=>$data['tenant_mode'], 'TENANT_DB_HOST'=>$data['db_host'], 'TENANT_DB_PORT'=>(string)$data['db_port'], 'TENANT_DB_USERNAME'=>$data['db_username'], 'TENANT_DB_PASSWORD'=>$data['db_password']??'',
            'MAIL_MAILER'=>$data['mail_mailer'], 'MAIL_HOST'=>$data['mail_host']??'', 'MAIL_PORT'=>(string)($data['mail_port']??587), 'MAIL_USERNAME'=>$data['mail_username']??'', 'MAIL_PASSWORD'=>$data['mail_password']??'', 'MAIL_FROM_ADDRESS'=>$data['mail_from_address'],
            'QUEUE_CONNECTION'=>'database', 'CACHE_STORE'=>'database', 'SESSION_DRIVER'=>'database',
        ];
        $this->writeEnvironment($values);
        config(['app.name'=>$data['app_name'],'app.url'=>$data['app_url'],'database.connections.central'=>array_merge(config('database.connections.central'),['host'=>$data['db_host'],'port'=>$data['db_port'],'database'=>$data['db_database'],'username'=>$data['db_username'],'password'=>$data['db_password']??''])]);
        DB::purge('central');
        $this->finalize(['name'=>$data['admin_name'],'email'=>$data['admin_email'],'password'=>$data['admin_password'],'role'=>'super_admin','is_active'=>true,'email_verified_at'=>now()]);
    }

    private function writeEnvironment(array $values): void
    {
        $path = base_path('.env'); $content = File::exists($path) ? File::get($path) : File::get(base_path('.env.example'));
        foreach ($values as $key => $value) { $encoded='"'.str_replace(['\\','"',"\r","\n"],['\\\\','\\"','',''],(string)$value).'"'; $pattern='/^'.preg_quote($key,'/').'=.*$/m'; $content=preg_match($pattern,$content)?preg_replace($pattern,$key.'='.$encoded,$content):rtrim($content).PHP_EOL.$key.'='.$encoded.PHP_EOL; }
        File::put($path,$content);
    }

    public function installed(): bool
    {
        return file_exists(storage_path('installed'));
    }
}
