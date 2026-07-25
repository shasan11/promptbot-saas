<?php

namespace App\Services\Platform;

use App\Models\PlatformOperation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    public function __construct(
        private readonly RevenueMetricsService $revenue,
        private readonly TenantMetricsService $tenants,
        private readonly UsageMetricsService $usage,
    ) {}

    public function get(array $filters = []): array
    {
        return Cache::remember('superadmin.dashboard.'.md5(json_encode($filters)), now()->addMinutes(5), function (): array {
            $tenant = $this->tenants->summary();
            $revenue = $this->revenue->summary();
            $usage = $this->usage->summary();

            return [
                'stats' => [
                    'tenants' => $tenant['total'],
                    'activeTenants' => $tenant['active'],
                    'trialTenants' => $tenant['trial'],
                    'suspendedTenants' => $tenant['suspended'],
                    'activeSubscriptions' => $this->countWhere('subscriptions', 'status', 'active'),
                    'mrr' => $revenue['mrr'],
                    'arr' => $revenue['arr'],
                    'revenueCollectedThisMonth' => $revenue['collected_this_month'],
                    'outstandingInvoices' => $revenue['outstanding_invoices'],
                    'failedPayments' => $revenue['failed_payments'],
                    'messagesProcessed' => $usage['messages_processed'],
                    'aiTokensConsumed' => $usage['ai_tokens_consumed'],
                    'voiceMinutesConsumed' => $usage['voice_minutes_consumed'],
                    'storageUsed' => $usage['storage_used'],
                    'openSupportTickets' => $this->countWhere('support_tickets', 'status', 'open'),
                    'failedJobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                    'providerIncidents' => Schema::hasTable('provider_health_logs') ? DB::table('provider_health_logs')->whereIn('status', ['warning', 'critical'])->count() : 0,
                ],
                'recentTenants' => $tenant['recent'],
                'subscriptionsByStatus' => Schema::hasTable('subscriptions')
                    ? DB::table('subscriptions')->selectRaw('status, count(*) as total')->groupBy('status')->orderBy('status')->get()
                    : collect(),
                'recentOperations' => PlatformOperation::query()->latest()->limit(8)->get(),
                'usageByMetric' => $usage['by_metric'],
            ];
        });
    }

    private function countWhere(string $table, string $column, string $value): int
    {
        return Schema::hasTable($table) ? DB::table($table)->where($column, $value)->count() : 0;
    }
}
