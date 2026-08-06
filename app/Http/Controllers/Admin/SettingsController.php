<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    private const GROUPS = [
        'general' => [
            'title' => 'General',
            'description' => 'Core platform identity, regional defaults, and support contact details.',
            'fields' => [
                'platform_name' => ['label' => 'Platform name', 'rules' => ['sometimes', 'required', 'string', 'max:255']],
                'platform_url' => ['label' => 'Platform URL', 'rules' => ['sometimes', 'nullable', 'url', 'max:255'], 'placeholder' => 'https://app.example.com'],
                'support_email' => ['label' => 'Support email', 'rules' => ['sometimes', 'nullable', 'email', 'max:255']],
                'timezone' => ['label' => 'Default timezone', 'rules' => ['sometimes', 'required', 'timezone'], 'placeholder' => 'UTC'],
                'default_locale' => ['label' => 'Default locale', 'rules' => ['sometimes', 'required', 'string', 'max:10'], 'placeholder' => 'en'],
                'default_currency' => ['label' => 'Default currency', 'rules' => ['sometimes', 'required', 'string', 'size:3'], 'placeholder' => 'USD'],
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
        'email' => [
            'title' => 'Email Identity',
            'description' => 'Sender identity and reply addresses used by system-generated email.',
            'fields' => [
                'from_name' => ['label' => 'From name', 'rules' => ['sometimes', 'required', 'string', 'max:255']],
                'from_address' => ['label' => 'From email address', 'rules' => ['sometimes', 'required', 'email', 'max:255']],
                'reply_to_name' => ['label' => 'Reply-to name', 'rules' => ['sometimes', 'nullable', 'string', 'max:255']],
                'reply_to_address' => ['label' => 'Reply-to email address', 'rules' => ['sometimes', 'nullable', 'email', 'max:255']],
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
        'ai_rag' => [
            'title' => 'AI & RAG',
            'description' => 'Model provider, embeddings, retrieval, chunking, and encrypted API credentials.',
            'fields' => [
                'ai_provider' => ['label' => 'AI provider', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:openai,anthropic,google,custom'], 'options' => [
                    ['value' => 'openai', 'label' => 'OpenAI'],
                    ['value' => 'anthropic', 'label' => 'Anthropic'],
                    ['value' => 'google', 'label' => 'Google'],
                    ['value' => 'custom', 'label' => 'Custom / OpenAI-compatible'],
                ]],
                'ai_model' => ['label' => 'Default chat model', 'rules' => ['sometimes', 'required', 'string', 'max:255']],
                'ai_base_url' => ['label' => 'Custom API base URL', 'rules' => ['sometimes', 'nullable', 'url', 'max:500']],
                'ai_api_key' => ['label' => 'AI API key', 'type' => 'password', 'sensitive' => true, 'rules' => ['sometimes', 'nullable', 'string', 'max:4096']],
                'embedding_model' => ['label' => 'Embedding model', 'rules' => ['sometimes', 'required', 'string', 'max:255']],
                'rag_vector_store' => ['label' => 'Vector store', 'type' => 'select', 'rules' => ['sometimes', 'required', 'in:pgvector,pinecone,qdrant,weaviate'], 'options' => [
                    ['value' => 'pgvector', 'label' => 'PostgreSQL / pgvector'],
                    ['value' => 'pinecone', 'label' => 'Pinecone'],
                    ['value' => 'qdrant', 'label' => 'Qdrant'],
                    ['value' => 'weaviate', 'label' => 'Weaviate'],
                ]],
                'rag_top_k' => ['label' => 'Retrieved chunks (top K)', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:1', 'max:100']],
                'rag_chunk_size' => ['label' => 'Chunk size', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:100', 'max:10000']],
                'rag_chunk_overlap' => ['label' => 'Chunk overlap', 'type' => 'number', 'rules' => ['sometimes', 'required', 'integer', 'min:0', 'max:5000']],
            ],
        ],
        'branding' => [
            'title' => 'Branding',
            'description' => 'Logos, colors, company naming, email identity, and footer copy.',
            'fields' => [
                'company_name' => ['label' => 'Company name', 'rules' => ['sometimes', 'required', 'string', 'max:255']],
                'logo_url' => ['label' => 'Primary logo URL', 'rules' => ['sometimes', 'nullable', 'url', 'max:500']],
                'logo_dark_url' => ['label' => 'Dark-mode logo URL', 'rules' => ['sometimes', 'nullable', 'url', 'max:500']],
                'favicon_url' => ['label' => 'Favicon URL', 'rules' => ['sometimes', 'nullable', 'url', 'max:500']],
                'email_logo_url' => ['label' => 'Email logo URL', 'rules' => ['sometimes', 'nullable', 'url', 'max:500']],
                'primary_color' => ['label' => 'Primary color', 'placeholder' => '#0F172A', 'rules' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                'secondary_color' => ['label' => 'Secondary color', 'placeholder' => '#4F46E5', 'rules' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                'accent_color' => ['label' => 'Accent color', 'placeholder' => '#22C55E', 'rules' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                'copyright_text' => ['label' => 'Copyright / footer text', 'type' => 'textarea', 'rules' => ['sometimes', 'nullable', 'string', 'max:1000']],
            ],
        ],
    ];

    public function edit(): Response
    {
        $groups = collect(self::GROUPS)->map(function (array $definition, string $group) {
            $fields = collect($definition['fields'])->map(function (array $field, string $key) use ($group) {
                $setting = PlatformSetting::query()->where('group', $group)->where('key', $key)->first();
                $sensitive = (bool) ($field['sensitive'] ?? false);

                return [
                    'key' => $key,
                    'label' => $field['label'],
                    'type' => $field['type'] ?? 'text',
                    'placeholder' => $field['placeholder'] ?? null,
                    'options' => $field['options'] ?? [],
                    'sensitive' => $sensitive,
                    'configured' => $sensitive && filled(data_get($setting?->value, 'value')),
                    'value' => $sensitive ? '' : data_get($setting?->value, 'value'),
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
        abort_unless(array_key_exists($group, self::GROUPS), 404);

        $fields = self::GROUPS[$group]['fields'];
        $rules = collect($fields)->mapWithKeys(fn (array $field, string $key) => [$key => $field['rules']])->all();
        $validated = $request->validate($rules);
        $oldValues = [];
        $newValues = [];

        foreach ($fields as $key => $field) {
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

        return back()->with('status', self::GROUPS[$group]['title'].' settings updated.');
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
}
