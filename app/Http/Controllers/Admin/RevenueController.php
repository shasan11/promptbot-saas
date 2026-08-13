<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Subscription;
use App\Services\Platform\CustomerPortalService;
use App\Services\Platform\PlatformSettingsService;
use Inertia\Inertia;
use Inertia\Response;

class RevenueController extends Controller
{
    public function __invoke(CustomerPortalService $portal, PlatformSettingsService $settings): Response
    {
        $currency = strtoupper((string) $settings->get('general', 'default_currency', 'USD'));
        $subscriptions = Subscription::with(['plan', 'tenant.customerAccount'])->get();
        $active = $subscriptions->filter(fn ($item) => in_array($item->status->value, ['active', 'trial', 'past_due'], true));
        $mrr = round($active->sum(fn ($item) => $portal->monthlyValue($item)), 2);
        $payments = Payment::with(['customerAccount', 'tenant'])->where('currency', $currency)->latest()->limit(20)->get();

        return Inertia::render('Admin/Revenue/Overview', [
            'currency' => $currency,
            'stats' => [
                'mrr' => $mrr, 'arr' => round($mrr * 12, 2),
                'revenue' => (float) Payment::where('currency', $currency)->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->sum('amount'),
                'outstanding' => (float) Invoice::where('currency', $currency)->whereIn('status', ['open', 'past_due'])->sum('total'),
                'refunds' => (float) PaymentRefund::where('status', 'completed')->whereHas('payment', fn ($query) => $query->where('currency', $currency))->sum('amount'),
                'failedPayments' => Payment::where('status', 'failed')->count(),
                'activeSubscriptions' => $active->count(), 'trials' => $subscriptions->filter(fn ($item) => $item->status->value === 'trial')->count(),
                'churnedSubscriptions' => $subscriptions->filter(fn ($item) => $item->status->value === 'cancelled')->count(),
            ],
            'planMix' => $active->groupBy(fn ($item) => $item->plan?->name ?: 'No plan')->map->count(),
            'recentPayments' => $payments,
        ]);
    }
}
