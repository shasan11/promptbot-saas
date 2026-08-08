<?php

namespace App\Services\Tenant;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Typed access to the tenant's flat key/value `settings` table, following the
 * dot-namespaced key convention already established by Tenant\Admin\SettingController
 * (e.g. "company.name", "branding"). Every write is cached per-tenant and
 * invalidated immediately, and optionally audited via the passed actor.
 */
class TenantSettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember($this->cacheKey($key), 3600, function () use ($key, $default) {
            $value = Setting::query()->where('key', $key)->value('value');

            return $value['value'] ?? $value ?? $default;
        });
    }

    public function set(string $key, mixed $value, ?User $actor = null, ?TenantAuditLogService $auditor = null): void
    {
        $before = $this->get($key);
        $stored = is_array($value) ? $value : ['value' => $value];

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
        Cache::forget($this->cacheKey($key));

        if ($auditor) {
            $auditor->record(
                event: 'workspace.setting_updated',
                actor: $actor,
                description: "Updated setting \"{$key}\"",
                oldValues: ['value' => $before],
                newValues: ['value' => $value],
                subjectType: 'setting',
                subjectLabel: $key,
            );
        }
    }

    /** @return array<string, mixed> */
    public function group(string $prefix): array
    {
        return Cache::remember($this->cacheKey("group:{$prefix}"), 3600, function () use ($prefix) {
            return Setting::query()
                ->where('key', 'like', "{$prefix}%")
                ->get()
                ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value['value'] ?? $setting->value])
                ->all();
        });
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
        Cache::forget($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        $tenantId = tenancy()->initialized ? tenant('id') : 'central';

        return 'tenant-settings:'.$tenantId.':'.Str::slug($key, '_');
    }
}
