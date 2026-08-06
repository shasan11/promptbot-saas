<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CentralUser;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlatformAdminLoginAttempt;
use App\Models\PlatformRole;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ConsolePageController extends Controller
{
    public function payments(): Response
    {
        return $this->page(
            'Payments',
            'Track payment collection, manual entries, refunds, and gateway settlement readiness.',
            [
                ['label' => 'Payment records', 'value' => $this->tableCount('payments'), 'status' => 'Ready'],
                ['label' => 'Payment attempts', 'value' => $this->tableCount('payment_attempts'), 'status' => 'Ready'],
                ['label' => 'Refunds', 'value' => $this->tableCount('refunds'), 'status' => 'Ready'],
            ],
            [
                [
                    'title' => 'Payment workflow',
                    'description' => 'Provider-independent payment models can be attached here when gateway credentials are configured.',
                    'items' => ['Manual payment capture', 'Refund review queue', 'Idempotent webhook status', 'Financial audit trail'],
                ],
            ]
        );
    }

    public function coupons(): Response
    {
        return $this->page('Coupons', 'Create and audit promotional discounts for subscriptions and invoices.', [
            ['label' => 'Coupons', 'value' => $this->tableCount('coupons'), 'status' => 'Ready'],
            ['label' => 'Active plans', 'value' => Plan::query()->where('is_active', true)->count(), 'status' => 'Live'],
        ], [
            ['title' => 'Discount controls', 'description' => 'Coupon redemption can be scoped by plan, tenant, billing interval, and expiration window.', 'items' => ['Percentage discounts', 'Fixed discounts', 'Redemption limits', 'Tenant-specific grants']],
        ]);
    }

    public function gateways(): Response
    {
        return $this->page('Payment Gateways', 'Configure payment providers with encrypted secrets and webhook processing.', [
            ['label' => 'Gateways', 'value' => $this->tableCount('gateways'), 'status' => 'Ready'],
            ['label' => 'Webhooks', 'value' => $this->tableCount('gateway_webhooks'), 'status' => 'Ready'],
        ], [
            ['title' => 'Gateway manager', 'description' => 'Secrets must be encrypted, masked in UI, and never shown after save.', 'items' => ['Stripe-compatible gateway slot', 'Webhook signing secret', 'Idempotency key tracking', 'Provider health logs']],
        ]);
    }

    public function usage(): Response
    {
        return $this->page('Usage Metering', 'Monitor usage metrics and tenant limit warnings across the platform.', [
            ['label' => 'Tenants', 'value' => Tenant::query()->count(), 'status' => 'Live'],
            ['label' => 'Features', 'value' => Feature::query()->count(), 'status' => 'Live'],
            ['label' => 'Usage rows', 'value' => $this->tableCount('usage_metrics'), 'status' => 'Ready'],
        ], [
            ['title' => 'Tracked dimensions', 'description' => 'Usage services are prepared around tenant-safe metric collection.', 'items' => ['Messages', 'AI tokens', 'Voice minutes', 'Storage', 'API requests', 'Automations']],
        ]);
    }

    public function communications(): Response
    {
        return $this->page('Communications', 'Manage templates, announcements, notifications, and test-send workflows.', [
            ['label' => 'Templates', 'value' => $this->tableCount('notification_templates'), 'status' => 'Ready'],
            ['label' => 'Announcements', 'value' => $this->tableCount('announcements'), 'status' => 'Ready'],
        ], [
            ['title' => 'Template lifecycle', 'description' => 'Templates can support variables, language variants, previews, draft versions, and published versions.', 'items' => ['Email templates', 'Notification templates', 'Variable preview', 'Test send']],
            ['title' => 'Announcements', 'description' => 'Target announcements by tenant, plan, country, trial/paid state, schedule, and expiry.', 'items' => ['Tenant targeting', 'Plan targeting', 'Scheduled publishing']],
        ]);
    }

    public function support(): Response
    {
        return $this->page('Support', 'Operate support tickets, internal notes, assignments, attachments, and SLA fields.', [
            ['label' => 'Tickets', 'value' => $this->tableCount('support_tickets'), 'status' => 'Ready'],
            ['label' => 'Tenants', 'value' => Tenant::query()->count(), 'status' => 'Live'],
        ], [
            ['title' => 'Support desk', 'description' => 'Tickets should stay linked to tenants and preserve internal notes separately from customer-visible replies.', 'items' => ['Assignment', 'Priority', 'SLA due date', 'Attachments', 'Internal notes']],
        ]);
    }

    public function integrations(): Response
    {
        return $this->page('Integrations and AI', 'Configure third-party integrations, AI models, provider health, and safe secret handling.', [
            ['label' => 'Integrations', 'value' => $this->tableCount('integration_definitions'), 'status' => 'Ready'],
            ['label' => 'AI models', 'value' => $this->tableCount('ai_model_configs'), 'status' => 'Ready'],
        ], [
            ['title' => 'Provider configuration', 'description' => 'Provider keys should be encrypted and masked, with health checks recorded separately.', 'items' => ['AI model routing', 'Provider health logs', 'Masked secrets', 'Integration status']],
        ]);
    }

    public function operations(): Response
    {
        return $this->page('Operations Health', 'Inspect application health, queues, scheduler, failed jobs, backups, and maintenance posture.', [
            ['label' => 'Database', 'value' => $this->databaseStatus(), 'status' => 'Live'],
            ['label' => 'Queue', 'value' => config('queue.default'), 'status' => 'Configured'],
            ['label' => 'Failed jobs', 'value' => $this->tableCount('failed_jobs'), 'status' => 'Live'],
            ['label' => 'Environment', 'value' => app()->environment(), 'status' => 'Live'],
        ], [
            ['title' => 'System checks', 'description' => 'These checks avoid querying arbitrary tenant tables and focus on central app readiness.', 'items' => ['Central database connection', 'Queue driver', 'Failed job table', 'Scheduler command readiness']],
            ['title' => 'Maintenance', 'description' => 'Maintenance windows and backups should be audited and require restricted permissions.', 'items' => ['Backup status', 'Maintenance banner', 'Restore notes']],
        ]);
    }

    public function auditLogs(): Response
    {
        $logs = AuditLog::query()
            ->with('administrator:id,name,email')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AuditLog $log) => [
                'Action' => $log->action,
                'Actor' => $log->administrator?->email ?? 'System',
                'Entity' => class_basename((string) $log->entity_type).' '.$log->entity_id,
                'Severity' => $log->severity,
                'Created' => optional($log->created_at)->toDateTimeString(),
            ]);

        return $this->page('Audit Logs', 'Review central administrator actions, reasons, actors, entities, IPs, and request UUIDs.', [
            ['label' => 'Audit events', 'value' => AuditLog::query()->count(), 'status' => 'Live'],
            ['label' => 'Recent window', 'value' => $logs->count(), 'status' => 'Showing'],
        ], [], $logs->all());
    }

    public function loginAttempts(): Response
    {
        $attempts = PlatformAdminLoginAttempt::query()
            ->with('administrator:id,name,email')
            ->latest('attempted_at')
            ->limit(100)
            ->get()
            ->map(fn (PlatformAdminLoginAttempt $attempt) => [
                'Email' => $attempt->email,
                'Result' => $attempt->successful ? 'Successful' : 'Failed',
                'Reason' => $attempt->failure_reason ?? '-',
                'IP' => $attempt->ip_address ?? '-',
                'Attempted' => optional($attempt->attempted_at)->toDateTimeString(),
            ]);

        return $this->page('Login Attempts', 'Inspect superadmin authentication attempts and failure reasons.', [
            ['label' => 'Attempts', 'value' => PlatformAdminLoginAttempt::query()->count(), 'status' => 'Live'],
            ['label' => 'Failed', 'value' => PlatformAdminLoginAttempt::query()->where('successful', false)->count(), 'status' => 'Live'],
        ], [], $attempts->all());
    }

    public function administrators(): Response
    {
        $admins = CentralUser::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (CentralUser $user) => [
                'Name' => $user->name,
                'Email' => $user->email,
                'Role' => $user->role,
                'Active' => $user->is_active ? 'Yes' : 'No',
                'Last login' => optional($user->last_login_at)->toDateTimeString() ?? '-',
            ]);

        return $this->page('Administrators', 'Manage platform operators and review account state.', [
            ['label' => 'Administrators', 'value' => CentralUser::query()->count(), 'status' => 'Live'],
            ['label' => 'Active', 'value' => CentralUser::query()->where('is_active', true)->count(), 'status' => 'Live'],
        ], [
            ['title' => 'Access model', 'description' => 'Access is granted entirely through Spatie roles and permissions — there is no email-based or role-string override.', 'items' => ['Platform Owner role syncs all permissions', 'Read-only auditor receives view-only permissions', 'Every Superadmin route enforces its exact permission server-side']],
        ], $admins->all());
    }

    public function roles(): Response
    {
        $roles = PlatformRole::query()
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (PlatformRole $role) => [
                'Role' => $role->name,
                'Guard' => $role->guard_name,
                'Permissions' => $role->permissions_count,
            ]);

        return $this->page('Roles and Permissions', 'Review platform roles and permission coverage.', [
            ['label' => 'Roles', 'value' => PlatformRole::query()->count(), 'status' => 'Live'],
            ['label' => 'Permissions', 'value' => $this->tableCount('platform_permissions'), 'status' => 'Live'],
        ], [], $roles->all());
    }

    public function security(): Response
    {
        return $this->page('Security', 'Manage lockouts, IP allowlists, CSP posture, signed URLs, uploads, and sensitive-action policy.', [
            ['label' => 'Login limit', 'value' => data_get(PlatformSetting::query()->where('group', 'security')->where('key', 'login_attempt_limit')->first()?->value, 'value', 5), 'status' => 'Configured'],
            ['label' => 'Locked admins', 'value' => CentralUser::query()->whereNotNull('locked_until')->count(), 'status' => 'Live'],
        ], [
            ['title' => 'Sensitive actions', 'description' => 'Danger-zone actions should require password reconfirmation and an auditable reason.', 'items' => ['Password reconfirmation', 'Optional second-admin approval', 'IP allowlists', 'CSP settings', 'Upload validation']],
        ]);
    }

    private function page(string $title, string $subtitle, array $cards = [], array $sections = [], array $rows = []): Response
    {
        return Inertia::render('Admin/ConsolePage', [
            'title' => $title,
            'subtitle' => $subtitle,
            'cards' => $cards,
            'sections' => $sections,
            'rows' => $rows,
        ]);
    }

    private function tableCount(string $table): string|int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 'Not installed';
    }

    private function databaseStatus(): string
    {
        try {
            DB::connection()->getPdo();

            return 'Connected';
        } catch (Throwable) {
            return 'Offline';
        }
    }
}
