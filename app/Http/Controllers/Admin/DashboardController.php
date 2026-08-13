<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\CustomerAccount;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\CustomerPortalService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(PlatformSettingsService $settings, CustomerPortalService $portal): Response
    {
        $currency = strtoupper((string) $settings->get('general', 'default_currency', 'USD'));
        $grossCollected = (float) Payment::query()
            ->where('currency', $currency)
            ->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->sum('amount');
        $refunded = (float) Payment::query()->where('currency', $currency)->sum('refunded_amount');
        $recurring = Subscription::with('plan')->whereIn('status', ['trial', 'active', 'past_due'])->get();
        $mrr = round($recurring->sum(fn (Subscription $subscription) => $portal->monthlyValue($subscription)), 2);

        return Inertia::render('Admin/Dashboard', [
            'currency' => $currency,
            'stats' => [
                'tenants' => Tenant::query()->count(),
                'customerAccounts' => CustomerAccount::query()->count(),
                'activeTenants' => Tenant::query()->where('status', TenantStatus::Active)->count(),
                'plans' => Plan::query()->where('is_active', true)->count(),
                'activeSubscriptions' => Subscription::query()->whereIn('status', ['trial', 'active', 'manual'])->count(),
                'outstandingInvoices' => Invoice::query()->whereIn('status', ['draft', 'open'])->count(),
                'netCollected' => max(0, $grossCollected - $refunded),
                'mrr' => $mrr,
                'arr' => round($mrr * 12, 2),
                'revenueThisMonth' => (float) Payment::query()->where('currency', $currency)->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
                'outstandingAmount' => (float) Invoice::query()->where('currency', $currency)->whereIn('status', ['open', 'past_due'])->sum('total'),
                'failedPayments' => Payment::query()->where('status', 'failed')->count(),
                'trialsEndingSoon' => Subscription::query()->where('status', 'trial')->whereBetween('trial_ends_at', [now(), now()->addDays(7)])->count(),
                'openTickets' => SupportTicket::query()->whereIn('status', ['open', 'pending'])->count(),
            ],
            'recentAccounts' => CustomerAccount::query()->with('owner:id,name,email')->withCount('tenants')->latest()->limit(6)->get(),
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
