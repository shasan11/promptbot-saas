<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PlatformSettingsService
{
    private bool $loaded = false;

    private array $values = [];

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $this->load();

        return $this->values[$group][$key] ?? $default;
    }

    public function group(string $group): array
    {
        $this->load();

        return $this->values[$group] ?? [];
    }

    public function clear(): self
    {
        $this->loaded = false;
        $this->values = [];

        return $this;
    }

    public function apply(): void
    {
        $general = $this->group('general');
        $email = $this->group('email');
        $mail = $this->group('mail');
        $payment = $this->group('payment');
        $aiRag = $this->group('ai_rag');
        $branding = $this->group('branding');
        $encryption = filled($mail['smtp_encryption'] ?? null) ? $mail['smtp_encryption'] : null;

        if (filled($general['platform_name'] ?? null)) {
            config(['app.name' => $general['platform_name']]);
        }

        if (filled($general['platform_url'] ?? null)) {
            config(['app.url' => $general['platform_url']]);
        }

        if (filled($general['default_locale'] ?? null)) {
            config(['app.locale' => $general['default_locale']]);
            app()->setLocale($general['default_locale']);
        }

        if (filled($general['timezone'] ?? null)) {
            config(['app.timezone' => $general['timezone']]);
            date_default_timezone_set($general['timezone']);
        }

        if (filled($general['tenant_base_domain'] ?? null)) {
            config(['saas.tenant_base_domain' => strtolower($general['tenant_base_domain'])]);
        }

        if (filled($mail['mailer'] ?? null)) {
            config(['mail.default' => $mail['mailer']]);
        }

        config([
            'mail.mailers.smtp.host' => $mail['smtp_host'] ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => isset($mail['smtp_port']) ? (int) $mail['smtp_port'] : config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.username' => $mail['smtp_username'] ?? config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $mail['smtp_password'] ?? config('mail.mailers.smtp.password'),
            'mail.mailers.smtp.scheme' => $encryption,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.from.address' => $email['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $email['from_name'] ?? config('mail.from.name'),
            'mail.reply_to.address' => $email['reply_to_address'] ?? null,
            'mail.reply_to.name' => $email['reply_to_name'] ?? null,
            'platform.support_email' => $general['support_email'] ?? null,
            'platform.default_currency' => strtoupper((string) ($general['default_currency'] ?? 'USD')),
            'platform.payment' => $payment,
            'platform.ai_rag' => $aiRag,
            'platform.branding' => $branding,
        ]);
    }

    public function publicBranding(): array
    {
        return [
            'name' => $this->get('general', 'platform_name', config('app.name', 'PromptBot')),
            'companyName' => $this->get('branding', 'company_name', $this->get('general', 'platform_name', 'PromptBot')),
            'logoUrl' => $this->get('branding', 'logo_url'),
            'logoDarkUrl' => $this->get('branding', 'logo_dark_url'),
            'faviconUrl' => $this->get('branding', 'favicon_url'),
            'primaryColor' => $this->get('branding', 'primary_color', '#0F172A'),
            'secondaryColor' => $this->get('branding', 'secondary_color', '#4F46E5'),
            'accentColor' => $this->get('branding', 'accent_color', '#22C55E'),
            'copyrightText' => $this->get('branding', 'copyright_text'),
        ];
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        try {
            if (! Schema::hasTable('platform_settings')) {
                return;
            }

            PlatformSetting::query()
                ->get(['group', 'key', 'value'])
                ->each(function (PlatformSetting $setting): void {
                    $this->values[$setting->group][$setting->key] = data_get($setting->value, 'value');
                });
        } catch (Throwable) {
            $this->values = [];
        }
    }
}
