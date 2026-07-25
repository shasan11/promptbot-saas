<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RevenueMetricsService
{
    public function summary(): array
    {
        return [
            'mrr' => $this->subscriptionRevenue('monthly'),
            'arr' => $this->subscriptionRevenue('annual') + ($this->subscriptionRevenue('monthly') * 12),
            'collected_this_month' => $this->paymentsThisMonth(),
            'outstanding_invoices' => $this->invoiceCount(['open', 'sent', 'overdue']),
            'failed_payments' => $this->paymentCount(['failed']),
        ];
    }

    private function subscriptionRevenue(string $interval): float
    {
        if (! Schema::hasTable('subscriptions')) {
            return 0.0;
        }

        $priceColumn = $interval === 'annual' ? 'annual_price' : 'monthly_price';

        return (float) DB::table('subscriptions')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'active')
            ->where('subscriptions.billing_interval', $interval)
            ->sum("plans.$priceColumn");
    }

    private function paymentsThisMonth(): float
    {
        return Schema::hasTable('payments')
            ? (float) DB::table('payments')->where('status', 'succeeded')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount')
            : 0.0;
    }

    private function invoiceCount(array $statuses): int
    {
        return Schema::hasTable('invoices') ? DB::table('invoices')->whereIn('status', $statuses)->count() : 0;
    }

    private function paymentCount(array $statuses): int
    {
        return Schema::hasTable('payments') ? DB::table('payments')->whereIn('status', $statuses)->count() : 0;
    }
}
