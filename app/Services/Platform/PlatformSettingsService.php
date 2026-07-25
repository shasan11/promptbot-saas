<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;

class PlatformSettingsService
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return PlatformSetting::query()->where('group', $group)->where('key', $key)->first()?->value['value'] ?? $default;
    }

    public function set(string $group, string $key, mixed $value, bool $sensitive = false): PlatformSetting
    {
        return PlatformSetting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => ['value' => $value], 'encrypted' => $sensitive, 'is_sensitive' => $sensitive],
        );
    }

    public function getGroup(string $group): array
    {
        return PlatformSetting::query()
            ->where('group', $group)
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (PlatformSetting $setting) => [
                $setting->key => $setting->is_sensitive ? '[masked]' : ($setting->value['value'] ?? null),
            ])
            ->all();
    }

    public function setGroup(string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            $sensitive = preg_match('/password|secret|token|key|credential/i', (string) $key) === 1;
            $this->set($group, (string) $key, $value, $sensitive);
        }
    }

    public function delete(string $group, string $key): void
    {
        PlatformSetting::query()->where('group', $group)->where('key', $key)->delete();
    }

    public function maskSensitive(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (preg_match('/password|secret|token|key|credential/i', (string) $key)) {
                $settings[$key] = '[masked]';
            }
        }

        return $settings;
    }

    public function export(): array
    {
        return PlatformSetting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group')->map(fn ($settings) => $settings->mapWithKeys(fn (PlatformSetting $setting) => [
            $setting->key => $setting->is_sensitive ? '[masked]' : ($setting->value['value'] ?? null),
        ])->all())->all();
    }

    public function import(array $groups): void
    {
        foreach ($groups as $group => $values) {
            $this->setGroup((string) $group, (array) $values);
        }
    }
}
