<?php

namespace App\Console\Commands;

use App\Models\CentralUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class PromptbotProductionCheckCommand extends Command
{
    protected $signature = 'promptbot:production-check';

    protected $description = 'Check PromptBot central platform production readiness.';

    public function handle(): int
    {
        $checks = [
            'APP_DEBUG disabled' => ! config('app.debug'),
            'HTTPS configured' => str_starts_with((string) config('app.url'), 'https://') || ! app()->environment('production'),
            'Queue not sync in production' => ! app()->environment('production') || config('queue.default') !== 'sync',
            'Central database connected' => $this->databaseConnected(),
            'Cache configured' => filled(config('cache.default')),
            'Mail configured' => filled(config('mail.default')),
            'Session encryption recommended' => (bool) config('session.encrypt'),
            'Central users table exists' => Schema::hasTable('central_users'),
            'Platform owner has mandatory 2FA' => $this->ownersHaveRequiredTwoFactor(),
            'No default admin@example.com owner' => ! CentralUser::query()->where('email', 'admin@example.com')->exists(),
            'Central domains configured' => count(config('tenancy.central_domains', [])) > 0,
            'Tenant base domain configured' => filled(config('saas.tenant_base_domain')),
        ];

        $failed = 0;

        foreach ($checks as $label => $passed) {
            $passed ? $this->info('[ok] '.$label) : $this->error('[fail] '.$label);
            $failed += $passed ? 0 : 1;
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function databaseConnected(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function ownersHaveRequiredTwoFactor(): bool
    {
        try {
            $owners = CentralUser::role('Platform Owner', 'central')->get();
        } catch (RoleDoesNotExist) {
            return false;
        }

        return $owners->isNotEmpty() && $owners->every(fn (CentralUser $user) => $user->two_factor_required);
    }
}
