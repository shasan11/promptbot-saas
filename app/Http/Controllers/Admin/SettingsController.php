<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\PlatformSettingsService;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    private const CURRENCIES = [
        'USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'INR' => 'Indian Rupee',
        'NPR' => 'Nepalese Rupee', 'AUD' => 'Australian Dollar', 'CAD' => 'Canadian Dollar', 'JPY' => 'Japanese Yen',
        'CNY' => 'Chinese Yuan', 'CHF' => 'Swiss Franc', 'SGD' => 'Singapore Dollar', 'AED' => 'UAE Dirham',
        'SAR' => 'Saudi Riyal', 'ZAR' => 'South African Rand', 'BRL' => 'Brazilian Real', 'MXN' => 'Mexican Peso',
        'KRW' => 'South Korean Won', 'IDR' => 'Indonesian Rupiah', 'PHP' => 'Philippine Peso', 'THB' => 'Thai Baht',
        'VND' => 'Vietnamese Dong', 'PKR' => 'Pakistani Rupee', 'BDT' => 'Bangladeshi Taka', 'LKR' => 'Sri Lankan Rupee',
        'NZD' => 'New Zealand Dollar', 'SEK' => 'Swedish Krona', 'NOK' => 'Norwegian Krone', 'DKK' => 'Danish Krone',
        'PLN' => 'Polish Zloty', 'TRY' => 'Turkish Lira', 'HKD' => 'Hong Kong Dollar',
    ];

    private const LOCALES = [
        'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese',
        'it' => 'Italian', 'nl' => 'Dutch', 'ru' => 'Russian', 'zh' => 'Chinese', 'ja' => 'Japanese',
        'ko' => 'Korean', 'ar' => 'Arabic', 'hi' => 'Hindi', 'ne' => 'Nepali', 'bn' => 'Bengali',
    ];

    private const IMAGE_MAX_KILOBYTES = 2048;

    public function edit(): Response
    {
        $groups = collect($this->groups())->map(function (array $definition, string $group) {
            $fields = collect($definition['fields'])->map(function (array $field, string $key) use ($group) {
                $setting = PlatformSetting::query()->where('group', $group)->where('key', $key)->first();
                $sensitive = (bool) ($field['sensitive'] ?? false);
                $type = $field['type'] ?? 'text';
                $value = data_get($setting?->value, 'value');

                return [
                    'key' => $key,
                    'label' => $field['label'],
                    'type' => $type,
                    'placeholder' => $field['placeholder'] ?? null,
                    'help' => $field['help'] ?? null,
                    'options' => $field['options'] ?? [],
                    'accept' => $field['accept'] ?? null,
                    'sensitive' => $sensitive,
                    'configured' => $sensitive && filled($value),
                    'value' => $type === 'image' ? null : ($sensitive ? '' : $value),
                    'currentImageUrl' => $type === 'image' ? $value : null,
                ];
            })->values();

            return [
                'key' => $group,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'fields' => $fields,
            ];
        })->values();

        return Inertia::render('Admin/Settings/Index', ['groups' => $groups]);
    }

    public function update(
        Request $request,
        string $group,
        PlatformSettingsService $settings,
        AuditLogService $auditLog
    ): RedirectResponse {
        $groups = $this->groups();
        abort_unless(array_key_exists($group, $groups), 404);

        $fields = $groups[$group]['fields'];
        $imageKeys = collect($fields)->filter(fn (array $field) => ($field['type'] ?? null) === 'image')->keys();

        $rules = collect($fields)
            ->reject(fn (array $field, string $key) => $imageKeys->contains($key))
            ->mapWithKeys(fn (array $field, string $key) => [$key => $field['rules']])
            ->all();

        foreach ($imageKeys as $key) {
            $rules[$key] = ['nullable', 'image', 'max:'.self::IMAGE_MAX_KILOBYTES];
            $rules["remove_{$key}"] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);
        $oldValues = [];
        $newValues = [];

        foreach ($fields as $key => $field) {
            $type = $field['type'] ?? 'text';

            if ($type === 'image') {
                $this->updateImageSetting($request, $group, $key, $oldValues, $newValues);

                continue;
            }

            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $sensitive = (bool) ($field['sensitive'] ?? false);
            $value = $validated[$key];

            if ($sensitive && blank($value)) {
                continue;
            }

            if ($key === 'default_currency' && is_string($value)) {
                $value = strtoupper($value);
            }

            $setting = PlatformSetting::query()->where('group', $group)->where('key', $key)->first();
            $oldValues[$key] = $sensitive && $setting ? '[configured]' : data_get($setting?->value, 'value');
            $newValues[$key] = $sensitive ? '[updated]' : $value;

            PlatformSetting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                [
                    'id' => $setting?->id ?? (string) Str::uuid(),
                    'value' => ['value' => $value],
                    'encrypted' => $sensitive,
                    'is_sensitive' => $sensitive,
                ]
            );
        }

        $auditLog->record('platform_settings.updated', null, [
            'entity_type' => 'PlatformSetting',
            'entity_id' => $group,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        $settings->clear()->apply();

        return back()->with('status', $groups[$group]['title'].' settings updated.');
    }

    public function testMail(
        Request $request,
        PlatformSettingsService $settings,
        AuditLogService $auditLog
    ): RedirectResponse {
        $validated = $request->validate([
            'recipient' => ['required', 'email', 'max:255'],
        ]);

        $settings->clear()->apply();

        try {
            Mail::raw(
                'This is a test message from '.config('app.name').'. Your mail delivery settings are working.',
                fn ($message) => $message->to($validated['recipient'])->subject(config('app.name').' mail test')
            );

            $auditLog->record('platform_settings.mail_test_succeeded', null, [
                'entity_type' => 'PlatformSetting',
                'entity_id' => 'mail',
                'new_values' => ['recipient' => $validated['recipient']],
            ]);

            return back()->with('status', 'Test email sent to '.$validated['recipient'].'.');
        } catch (Throwable $exception) {
            report($exception);

            $auditLog->record('platform_settings.mail_test_failed', null, [
                'entity_type' => 'PlatformSetting',
                'entity_id' => 'mail',
                'new_values' => ['recipient' => $validated['recipient']],
                'severity' => 'warning',
            ]);

            return back()->with('error', 'Mail test failed. Check the SMTP settings and application logs.');
        }
    }

    /**
     * A file input can only ever submit a brand-new file, never the existing
     * value (browsers won't let you pre-fill one), so image settings are
     * handled outside the normal validated-value loop: a new upload replaces
     * the stored URL, a "remove" checkbox clears it, and anything else
     * leaves the current value untouched.
     */
    private function updateImageSetting(Request $request, string $group, string $key, array &$oldValues, array &$newValues): void
    {
        $setting = PlatformSetting::query()->where('group', $group)->where('key', $key)->first();
        $currentUrl = data_get($setting?->value, 'value');

        if ($request->hasFile($key)) {
            $path = $request->file($key)->store('branding', 'public');
            $url = Storage::disk('public')->url($path);

            $oldValues[$key] = $currentUrl;
            $newValues[$key] = $url;

            PlatformSetting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['id' => $setting?->id ?? (string) Str::uuid(), 'value' => ['value' => $url]]
            );

            if ($currentUrl && $setting) {
                $this->forgetStoredFile($currentUrl);
            }

            return;
        }

        if ($request->boolean("remove_{$key}") && $setting) {
            $oldValues[$key] = $currentUrl;
            $newValues[$key] = null;

            $setting->update(['value' => ['value' => null]]);
            $this->forgetStoredFile($currentUrl);
        }
    }

    private function forgetStoredFile(?string $url): void
    {
        if (! $url) {
            return;
        }

        $publicPrefix = Storage::disk('public')->url('');
        if (! str_starts_with($url, $publicPrefix)) {
            return;
        }

        Storage::disk('public')->delete(Str::after($url, $publicPrefix));
    }

    /**
     * @return array<string, array{title: string, description: string, fields: array<string, array>}>
     */
    private function groups(): array
    {
        return [
            'general' => [
                'title' => 'General',
                'description' => 'Core platform identity, regional defaults, and support contact details.',
                'fields' => [
                    'platform_name' => ['label' => 'Platform name', 'rules' => ['sometimes', 'required', 'string', 'max:255']],
                    'platform_url' => ['label' => 'Platform URL', 'type' => 'url', 'rules' => ['sometimes', 'nullable', 'url', 'max:255'], 'placeholder' => 'https://app.example.com'],
                    'legal_company_name' => ['label' => 'Company / legal name', 'rules' => ['sometimes', 'nullable', 'string', 'max:255']],
                    'support_email' => ['label' => 'Support email', 'type' => 'email', 'rules' => ['sometimes', 'nullable', 'email', 'max:255']],
                    'billing_email' => ['label' => 'Billing email', 'type' => 'email', 'rules' => ['sometimes', 'nullable', 'email', 'max:255']],
                    'timezone' => ['label' => 'Default timezone', 'type' => 'select', 'rules' => ['sometimes', 'required', 'timezone'], 'options' => $this->timezoneOptions()],
                    'default_locale' => ['label' => 'Default locale', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:'.implode(',', array_keys(self::LOCALES))], 'options' => $this->mapOptions(self::LOCALES)],
                    'default_currency' => ['label' => 'Default currency', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:'.implode(',', array_keys(self::CURRENCIES))], 'options' => $this->mapOptions(self::CURRENCIES, true)],
                    'tenant_base_domain' => [
                        'label' => 'Tenant base domain',
                        'rules' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i'],
                        'placeholder' => 'localhost',
                        'help' => 'New tenants get {slug}.{this domain} as their subdomain, e.g. "acme.localhost" for local development or "acme.yourapp.com" in production (requires wildcard DNS).',
                    ],
                ],
            ],
            'security' => [
                'title' => 'Security',
                'description' => 'Login throttling, account lockout duration, and administrator password-expiry policy.',
                'fields' => [
                    'login_attempt_limit' => ['label' => 'Login attempt limit', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:3', 'max:20']],
                    'lockout_duration_minutes' => ['label' => 'Lockout duration (minutes)', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:1', 'max:1440']],
                    'password_expiry_days' => ['label' => 'Password expiry (days)', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:0', 'max:365']],
                ],
            ],
            'localization' => [
                'title' => 'Localization',
                'description' => 'Regional defaults used in the customer portal, invoices, and operational reports.',
                'fields' => [
                    'timezone' => ['label' => 'Default timezone', 'type' => 'select', 'rules' => ['sometimes', 'required', 'timezone'], 'options' => $this->timezoneOptions()],
                    'locale' => ['label' => 'Default language', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:'.implode(',', array_keys(self::LOCALES))], 'options' => $this->mapOptions(self::LOCALES)],
                    'country' => ['label' => 'Default country code', 'rules' => ['sometimes', 'nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/']],
                    'date_format' => ['label' => 'Date format', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:Y-m-d,d/m/Y,m/d/Y,d M Y'], 'options' => $this->mapOptions(['Y-m-d' => '2026-08-12', 'd/m/Y' => '12/08/2026', 'm/d/Y' => '08/12/2026', 'd M Y' => '12 Aug 2026'])],
                    'currency' => ['label' => 'Default currency', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:'.implode(',', array_keys(self::CURRENCIES))], 'options' => $this->mapOptions(self::CURRENCIES, true)],
                ],
            ],
            'email' => [
                'title' => 'Email Identity',
                'description' => 'Sender identity and reply addresses used by system-generated email.',
                'fields' => [
                    'from_name' => ['label' => 'From name', 'rules' => ['sometimes', 'required', 'string', 'max:255']],
                    'from_address' => ['label' => 'From email address', 'type' => 'email', 'rules' => ['sometimes', 'required', 'email', 'max:255']],
                    'reply_to_name' => ['label' => 'Reply-to name', 'rules' => ['sometimes', 'nullable', 'string', 'max:255']],
                    'reply_to_address' => ['label' => 'Reply-to email address', 'type' => 'email', 'rules' => ['sometimes', 'nullable', 'email', 'max:255']],
                ],
            ],
            'mail' => [
                'title' => 'Mail Delivery',
                'description' => 'SMTP transport and authentication used to deliver platform email.',
                'fields' => [
                    'mailer' => ['label' => 'Mailer', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:smtp,log,array'], 'options' => [
                        ['value' => 'smtp', 'label' => 'SMTP'],
                        ['value' => 'log', 'label' => 'Log only'],
                        ['value' => 'array', 'label' => 'Array / testing'],
                    ]],
                    'smtp_host' => ['label' => 'SMTP host', 'rules' => ['sometimes', 'nullable', 'string', 'max:255']],
                    'smtp_port' => ['label' => 'SMTP port', 'type' => 'number', 'rules' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535']],
                    'smtp_username' => ['label' => 'SMTP username', 'rules' => ['sometimes', 'nullable', 'string', 'max:255']],
                    'smtp_password' => ['label' => 'SMTP password', 'type' => 'password', 'sensitive' => true, 'rules' => ['sometimes', 'nullable', 'string', 'max:2048']],
                    'smtp_encryption' => ['label' => 'SMTP encryption', 'type' => 'select', 'rules' => ['sometimes', 'nullable', 'in:tls,ssl'], 'options' => [
                        ['value' => '', 'label' => 'None'],
                        ['value' => 'tls', 'label' => 'TLS'],
                        ['value' => 'ssl', 'label' => 'SSL'],
                    ]],
                ],
            ],
            'payment' => [
                'title' => 'Payments',
                'description' => 'Gateway defaults, invoice numbering, tax, and encrypted provider credentials.',
                'fields' => [
                    'default_gateway' => ['label' => 'Default gateway', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:manual,stripe,paypal,khalti,esewa'], 'options' => [
                        ['value' => 'manual', 'label' => 'Manual'],
                        ['value' => 'stripe', 'label' => 'Stripe'],
                        ['value' => 'paypal', 'label' => 'PayPal'],
                        ['value' => 'khalti', 'label' => 'Khalti'],
                        ['value' => 'esewa', 'label' => 'eSewa'],
                    ]],
                    'invoice_prefix' => ['label' => 'Invoice prefix', 'rules' => ['sometimes', 'required', 'string', 'max:20'], 'placeholder' => 'INV'],
                    'tax_rate' => ['label' => 'Default tax rate (%)', 'type' => 'number', 'rules' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100']],
                    'gateway_public_key' => ['label' => 'Gateway public key', 'rules' => ['sometimes', 'nullable', 'string', 'max:2048']],
                    'gateway_secret_key' => ['label' => 'Gateway secret key', 'type' => 'password', 'sensitive' => true, 'rules' => ['sometimes', 'nullable', 'string', 'max:4096']],
                    'gateway_webhook_secret' => ['label' => 'Webhook signing secret', 'type' => 'password', 'sensitive' => true, 'rules' => ['sometimes', 'nullable', 'string', 'max:4096']],
                ],
            ],
            'billing' => [
                'title' => 'Billing Policy',
                'description' => 'Account billing modes, payment terms, grace periods, proration, and cancellation policy.',
                'fields' => [
                    'invoice_number_start' => ['label' => 'Next invoice sequence baseline', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999999']],
                    'payment_terms_days' => ['label' => 'Payment terms (days)', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:0', 'max:365']],
                    'grace_period_days' => ['label' => 'Failed-payment grace period (days)', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:0', 'max:365']],
                    'billing_mode_support' => ['label' => 'Billing modes', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:per_service,both'], 'options' => [['value' => 'per_service', 'label' => 'Per-service only'], ['value' => 'both', 'label' => 'Per-service and consolidated']]],
                    'proration_policy' => ['label' => 'Proration policy', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:none,immediate,next_invoice'], 'options' => [['value' => 'none', 'label' => 'No proration'], ['value' => 'immediate', 'label' => 'Immediate adjustment'], ['value' => 'next_invoice', 'label' => 'Adjust next invoice']]],
                    'plan_change_policy' => ['label' => 'Default plan-change timing', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:immediate,period_end,customer_choice'], 'options' => [['value' => 'immediate', 'label' => 'Immediate'], ['value' => 'period_end', 'label' => 'At period end'], ['value' => 'customer_choice', 'label' => 'Let eligible customer choose']]],
                    'allow_immediate_cancellation' => ['label' => 'Allow immediate cancellation', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                ],
            ],
            'tax' => [
                'title' => 'Tax',
                'description' => 'Default platform tax behavior. Historical invoices keep their issued tax snapshots.',
                'fields' => [
                    'enabled' => ['label' => 'Tax calculation', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'default_rate' => ['label' => 'Default tax rate (%)', 'type' => 'number', 'rules' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100']],
                    'prices_include_tax' => ['label' => 'Plan prices include tax', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'tax_label' => ['label' => 'Tax label', 'rules' => ['sometimes', 'required', 'string', 'max:50']],
                ],
            ],
            'registration' => [
                'title' => 'Registration',
                'description' => 'Public signup, verification, payment gates, plan eligibility, and account workspace limits.',
                'fields' => [
                    'mode' => ['label' => 'Customer registration', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:enabled,disabled,invitation_only'], 'options' => [['value' => 'enabled', 'label' => 'Enabled'], ['value' => 'disabled', 'label' => 'Disabled'], ['value' => 'invitation_only', 'label' => 'Invitation only']]],
                    'email_verification_required' => ['label' => 'Require email verification', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'require_payment_before_provisioning' => ['label' => 'Require payment before provisioning', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'allowed_plan_ids' => ['label' => 'Allowed public plan IDs', 'placeholder' => 'Leave blank for all public plans; otherwise comma-separated numeric IDs', 'rules' => ['sometimes', 'nullable', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/']],
                    'maximum_workspaces_per_account' => ['label' => 'Maximum workspaces per account (0 = unlimited)', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000']],
                ],
            ],
            'trials' => [
                'title' => 'Trials',
                'description' => 'Default trial duration and customer reminders.',
                'fields' => [
                    'default_trial_days' => ['label' => 'Default trial days', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:0', 'max:365']],
                    'trial_ending_notice_days' => ['label' => 'Trial-ending notice days', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:1', 'max:30']],
                    'allow_trial_without_payment_method' => ['label' => 'Allow trial without payment method', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                ],
            ],
            'tenant_provisioning' => [
                'title' => 'Tenant Provisioning',
                'description' => 'Defaults and operational retry behavior for new customer workspaces.',
                'fields' => [
                    'mode' => ['label' => 'Provisioning mode', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:manual,mysql,cpanel'], 'options' => [['value' => 'manual', 'label' => 'Manual database'], ['value' => 'mysql', 'label' => 'MySQL administrator'], ['value' => 'cpanel', 'label' => 'cPanel API']]],
                    'default_region' => ['label' => 'Default region', 'rules' => ['sometimes', 'nullable', 'string', 'max:100']],
                    'automatic_retry_count' => ['label' => 'Automatic retry count', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:0', 'max:10']],
                    'customer_can_retry' => ['label' => 'Allow customer-requested retry', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                ],
            ],
            'notifications' => [
                'title' => 'Notifications',
                'description' => 'Platform event delivery and reminder defaults.',
                'fields' => [
                    'billing_events_enabled' => ['label' => 'Billing event notifications', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'workspace_events_enabled' => ['label' => 'Workspace event notifications', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'support_events_enabled' => ['label' => 'Support reply notifications', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'renewal_notice_days' => ['label' => 'Renewal notice days', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:1', 'max:30']],
                ],
            ],
            'customer_portal' => [
                'title' => 'Customer Portal',
                'description' => 'Control customer-facing account, service, billing, member, and support capabilities.',
                'fields' => [
                    'enabled' => ['label' => 'Customer portal', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'google_login_enabled' => ['label' => 'Google customer login', 'type' => 'checkbox', 'help' => 'Shows Google sign-in after the credentials below are configured.', 'rules' => ['sometimes', 'boolean']],
                    'google_client_id' => ['label' => 'Google OAuth client ID', 'help' => 'Create a Web application credential in Google Cloud Console.', 'rules' => ['sometimes', 'nullable', 'string', 'max:1000']],
                    'google_client_secret' => ['label' => 'Google OAuth client secret', 'type' => 'password', 'sensitive' => true, 'help' => 'Stored encrypted. Leave blank to keep the configured secret.', 'rules' => ['sometimes', 'nullable', 'string', 'max:1000']],
                    'google_redirect_uri' => ['label' => 'Google OAuth redirect URL', 'type' => 'url', 'placeholder' => rtrim(config('app.url'), '/').'/account/oauth/google/callback', 'help' => 'Add this exact URL to the authorized redirect URIs in Google Cloud.', 'rules' => ['sometimes', 'nullable', 'url:http,https', 'max:2048']],
                    'allow_workspace_creation' => ['label' => 'Allow workspace creation', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'allow_plan_changes' => ['label' => 'Allow plan changes', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'allow_cancellations' => ['label' => 'Allow cancellations', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'allow_member_invitations' => ['label' => 'Allow member invitations', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'support_tickets_enabled' => ['label' => 'Support tickets', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                ],
            ],
            'maintenance' => [
                'title' => 'Maintenance',
                'description' => 'Customer-safe maintenance banners and operational access controls.',
                'fields' => [
                    'banner_enabled' => ['label' => 'Maintenance banner', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                    'banner_message' => ['label' => 'Banner message', 'type' => 'textarea', 'rules' => ['sometimes', 'nullable', 'string', 'max:2000']],
                    'block_new_provisioning' => ['label' => 'Pause new provisioning', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
                ],
            ],
            'branding' => [
                'title' => 'Branding',
                'description' => 'Logos, colors, company naming, email identity, and footer copy.',
                'fields' => [
                    'company_name' => ['label' => 'Company name', 'rules' => ['sometimes', 'required', 'string', 'max:255']],
                    'logo_url' => ['label' => 'Primary logo', 'type' => 'image', 'accept' => 'image/png,image/svg+xml,image/webp', 'help' => 'PNG, SVG, or WEBP. Max 2 MB.'],
                    'logo_dark_url' => ['label' => 'Dark-mode logo', 'type' => 'image', 'accept' => 'image/png,image/svg+xml,image/webp', 'help' => 'Shown when the admin panel or site is in dark mode.'],
                    'favicon_url' => ['label' => 'Favicon', 'type' => 'image', 'accept' => 'image/png,image/x-icon,image/svg+xml', 'help' => 'Square PNG or ICO, at least 32×32.'],
                    'email_logo_url' => ['label' => 'Email logo', 'type' => 'image', 'accept' => 'image/png,image/jpeg', 'help' => 'Used in the header of outgoing email templates.'],
                    'primary_color' => ['label' => 'Primary color', 'type' => 'color', 'placeholder' => '#0F172A', 'rules' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                    'secondary_color' => ['label' => 'Secondary color', 'type' => 'color', 'placeholder' => '#4F46E5', 'rules' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                    'accent_color' => ['label' => 'Accent color', 'type' => 'color', 'placeholder' => '#22C55E', 'rules' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                    'copyright_text' => ['label' => 'Copyright / footer text', 'type' => 'textarea', 'rules' => ['sometimes', 'nullable', 'string', 'max:1000']],
                ],
            ],
        ];
    }

    private function timezoneOptions(): array
    {
        return collect(DateTimeZone::listIdentifiers())
            ->map(fn (string $timezone) => ['value' => $timezone, 'label' => str_replace('_', ' ', $timezone)])
            ->values()
            ->all();
    }

    private function mapOptions(array $map, bool $codeFirst = false): array
    {
        return collect($map)
            ->map(fn (string $label, string $code) => ['value' => $code, 'label' => $codeFirst ? "{$code} — {$label}" : "{$label} ({$code})"])
            ->values()
            ->all();
    }
}
