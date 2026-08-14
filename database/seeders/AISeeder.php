<?php

namespace Database\Seeders;

use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed data for the Superadmin "AI & LLM" module: permissions, default
 * platform settings (group=ai / group=ai_features), and Operations Manager's
 * view-only grant. Kept independent of PlatformAuthorizationSeeder so it can
 * be re-run on its own (e.g. `php artisan db:seed --class=AISeeder`) when the
 * AI module is added to an existing install without reseeding everything else.
 *
 * PlatformAuthorizationSeeder merges permissions() into its own permission
 * list so Platform Owner/Administrator/Auditor still receive these via their
 * normal full-list sync; this seeder only needs to (re)create the permission
 * rows themselves and grant the narrower Operations Manager view access.
 */
class AISeeder extends Seeder
{
    /** @return array<int, string> */
    public static function permissions(): array
    {
        return [
            'ai.view',
            'ai.providers.view',
            'ai.providers.manage',
            'ai.models.view',
            'ai.models.manage',
            'ai.settings.view',
            'ai.settings.manage',
            'ai.features.manage',
            'ai.usage.view',
        ];
    }

    /** @return array<int, string> */
    public static function operationsManagerPermissions(): array
    {
        return ['ai.view', 'ai.providers.view', 'ai.models.view', 'ai.settings.view'];
    }

    public function run(): void
    {
        $permissionModels = collect(self::permissions())->mapWithKeys(fn (string $permission) => [
            $permission => PlatformPermission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'central'],
                ['id' => (string) Str::uuid()]
            ),
        ]);

        foreach (['Platform Owner', 'Platform Administrator'] as $roleName) {
            $role = PlatformRole::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'central'],
                ['id' => (string) Str::uuid()]
            );
            $role->givePermissionTo($permissionModels->values());
        }

        $operationsManager = PlatformRole::firstOrCreate(
            ['name' => 'Operations Manager', 'guard_name' => 'central'],
            ['id' => (string) Str::uuid()]
        );
        $operationsManager->givePermissionTo($permissionModels->only(self::operationsManagerPermissions())->values());

        $auditor = PlatformRole::where('name', 'Read-Only Auditor')->orWhere('name', 'Auditor')->get();
        foreach ($auditor as $role) {
            $role->givePermissionTo($permissionModels->filter(fn ($model, string $name) => str_ends_with($name, '.view'))->values());
        }

        $defaults = [
            'ai' => [
                'enabled' => false,
                'fallback_enabled' => true,
                'request_timeout_seconds' => 30,
                'max_retries' => 2,
                'circuit_breaker_enabled' => true,
                'circuit_breaker_failure_threshold' => 5,
                'circuit_breaker_cooldown_seconds' => 120,
                'log_requests' => true,
                'store_prompts' => false,
                'store_responses' => false,
                'monthly_budget_usd' => 0,
                'usage_retention_days' => 90,
                'allow_tenant_ai' => false,
                'allow_byok' => false,
            ],
            'ai_features' => [
                'knowledge_embeddings' => false,
                'knowledge_answers' => false,
                'inbox_reply_suggestions' => false,
                'automation_ai_actions' => false,
            ],
        ];

        foreach ($defaults as $group => $settings) {
            foreach ($settings as $key => $value) {
                PlatformSetting::firstOrCreate(
                    ['group' => $group, 'key' => $key],
                    [
                        'id' => (string) Str::uuid(),
                        'value' => ['value' => $value],
                        'encrypted' => false,
                        'is_sensitive' => false,
                    ]
                );
            }
        }
    }
}
