<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;

/**
 * Reads the `security` group of PlatformSetting rows so authentication
 * behavior (attempt limits, lockout duration, password expiry) is governed
 * by database-backed platform settings rather than hardcoded values, with
 * config/saas.php as the fallback when a setting has not been seeded yet.
 */
class SecuritySettings
{
    public function loginAttemptLimit(): int
    {
        return (int) $this->value('login_attempt_limit', config('saas.security.login_attempt_limit', 5));
    }

    public function lockoutDurationMinutes(): int
    {
        return (int) $this->value('lockout_duration_minutes', config('saas.security.lockout_duration_minutes', 15));
    }

    public function passwordExpiryDays(): int
    {
        return (int) $this->value('password_expiry_days', config('saas.security.password_expiry_days', 90));
    }

    private function value(string $key, mixed $default): mixed
    {
        return data_get(
            PlatformSetting::query()->where('group', 'security')->where('key', $key)->first()?->value,
            'value',
            $default
        );
    }
}
