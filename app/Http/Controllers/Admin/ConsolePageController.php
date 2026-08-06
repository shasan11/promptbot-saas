<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
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
            'Track tenant payments, failed attempts, refunds, and settlement status.',
            [
                ['label' => 'Payment records', 'value' => $this->tableCount('payments'), 'status' => 'Live'],
                ['label' => 'Pending payments', 'value' => $this->tableCountWhere('payments', 'status', 'pending'), 'status' => 'Live'],
                ['label' => 'Failed attempts', 'value' => $this->tableCountWhere('payment_attempts', 'status', 'failed'), 'status' => 'Live'],
                ['label' => 'Refunds', 'value' => $this->tableCount('refunds'), 'status' => 'Live'],
            ],
            [
                [
                    'title' => 'Payment operations',
                    'description' => 'Use this area for collection status, manual payment entries, refund review, and gateway reconciliation.',
                    'items' => ['Manual payment capture', 'Payment status tracking', 'Refund review', 'Settlement reconciliation', 'Webhook audit trail'],
                ],
            ]
        );
    }

    public function tickets(): Response
    {
        return $this->page(
            'Tickets',
            'Manage tenant support requests, assignments, priorities, internal notes, and SLA deadlines.',
            [
                ['label' => 'All tickets', 'value' => $this->tableCount('support_tickets'), 'status' => 'Live'],
                ['label' => 'Open tickets', 'value' => $this->tableCountWhere('support_tickets', 'status', 'open'), 'status' => 'Live'],
                ['label' => 'Tenants', 'value' => Tenant::query()->count(), 'status' => 'Live'],
            ],
            [
                [
                    'title' => 'Ticket workflow',
                    'description' => 'Tickets remain linked to a tenant and keep internal notes separate from customer-visible replies.',
                    'items' => ['Assignment', 'Priority', 'Status', 'SLA due date', 'Attachments', 'Internal notes'],
                ],
            ]
        );
    }

    public function reports(): Response
    {
        $rows = [
            ['Area' => 'Tenants', 'Metric' => 'Total tenants', 'Value' => Tenant::query()->count()],
            ['Area' => 'Tenants', 'Metric' => 'Active tenants', 'Value' => Tenant::query()->where('status', 'active')->count()],
            ['Area' => 'Billing', 'Metric' => 'Active plans', 'Value' => Plan::query()->where('is_active', true)->count()],
            ['Area' => 'Billing', 'Metric' => 'Subscriptions', 'Value' => $this->tableCount('subscriptions')],
            ['Area' => 'Billing', 'Metric' => 'Payments', 'Value' => $this->tableCount('payments')],
            ['Area' => 'Billing', 'Metric' => 'Invoices', 'Value' => $this->tableCount('invoices')],
            ['Area' => 'Support', 'Metric' => 'Tickets', 'Value' => $this->tableCount('support_tickets')],
        ];

        return $this->page(
            'Reports',
            'Review tenant growth, subscription activity, billing records, and support volume from one place.',
            [
                ['label' => 'Tenants', 'value' => Tenant::query()->count(), 'status' => 'Live'],
                ['label' => 'Subscriptions', 'value' => $this->tableCount('subscriptions'), 'status' => 'Live'],
                ['label' => 'Invoices', 'value' => $this->tableCount('invoices'), 'status' => 'Live'],
                ['label' => 'Tickets', 'value' => $this->tableCount('support_tickets'), 'status' => 'Live'],
            ],
            [
                [
                    'title' => 'Reporting scope',
                    'description' => 'This initial report hub keeps the metrics aligned with the streamlined superadmin scope.',
                    'items' => ['Tenant growth', 'Plan and subscription mix', 'Payment and invoice totals', 'Ticket volume', 'CSV/PDF export hooks'],
                ],
            ],
            $rows
        );
    }

    public function operations(): Response
    {
        return $this->page(
            'System Health',
            'Inspect the central database, queue, scheduler readiness, failed jobs, backups, and application environment.',
            [
                ['label' => 'Database', 'value' => $this->databaseStatus(), 'status' => 'Live'],
                ['label' => 'Queue', 'value' => config('queue.default'), 'status' => 'Configured'],
                ['label' => 'Failed jobs', 'value' => $this->tableCount('failed_jobs'), 'status' => 'Live'],
                ['label' => 'Environment', 'value' => app()->environment(), 'status' => 'Live'],
            ],
            [
                [
                    'title' => 'System checks',
                    'description' => 'Central health checks avoid arbitrary tenant-table access and focus on platform readiness.',
                    'items' => ['Central database connection', 'Queue driver', 'Failed jobs', 'Scheduler readiness', 'Cache and storage readiness'],
                ],
                [
                    'title' => 'Maintenance',
                    'description' => 'Maintenance and backup actions should remain restricted and auditable.',
                    'items' => ['Backup status', 'Maintenance mode', 'Restore notes', 'Queue retry controls'],
                ],
            ]
        );
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

    private function tableCountWhere(string $table, string $column, mixed $value): string|int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 'Not installed';
        }

        return DB::table($table)->where($column, $value)->count();
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
