<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Services\Platform\PlatformSettingsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(PlatformSettingsService $settings): Response
    {
        $currency = strtoupper((string) $settings->get('general', 'default_currency', 'USD'));
        $grossCollected = (float) Payment::query()
            ->where('currency', $currency)
            ->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->sum('amount');
        $refunded = (float) Payment::query()->where('currency', $currency)->sum('refunded_amount');

        return Inertia::render('Admin/Dashboard', [
            'currency' => $currency,
            'stats' => [
                'tenants' => Tenant::query()->count(),
                'activeTenants' => Tenant::query()->where('status', TenantStatus::Active)->count(),
                'plans' => Plan::query()->where('is_active', true)->count(),
                'activeSubscriptions' => Subscription::query()->whereIn('status', ['trial', 'active', 'manual'])->count(),
                'outstandingInvoices' => Invoice::query()->whereIn('status', ['draft', 'open'])->count(),
                'netCollected' => max(0, $grossCollected - $refunded),
                'openTickets' => SupportTicket::query()->whereIn('status', ['open', 'pending'])->count(),
            ],
            'recentTenants' => Tenant::query()
                ->with(['plan', 'domains'])
                ->latest()
                ->limit(6)
                ->get(),
            'subscriptionsByStatus' => Subscription::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'recentPayments' => Payment::query()
                ->with(['tenant:id,company_name', 'invoice:id,number'])
                ->latest()
                ->limit(6)
                ->get(),
            'urgentTickets' => SupportTicket::query()
                ->with(['tenant:id,company_name', 'assignee:id,name'])
                ->whereIn('status', ['open', 'pending'])
                ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
                ->latest('last_activity_at')
                ->limit(6)
                ->get(),
        ]);
    }
}
