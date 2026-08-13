<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class GrowthController extends Controller
{
    public function __invoke(): Response
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);
        $accounts = CustomerAccount::withTrashed()->where('created_at', '>=', $start)->get(['id', 'name', 'status', 'created_at', 'deleted_at']);
        $subscriptions = Subscription::query()
            ->with(['plan:id,name', 'customerAccount:id,name'])
            ->where(function ($query) use ($start): void {
                $query->where('created_at', '>=', $start)->orWhere('cancelled_at', '>=', $start);
            })->get();

        $months = collect(range(0, 11))->map(function (int $offset) use ($start, $accounts, $subscriptions): array {
            $month = $start->addMonths($offset);
            return [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'signups' => $accounts->filter(fn ($account) => $account->created_at?->format('Y-m') === $month->format('Y-m'))->count(),
                'trials' => $subscriptions->filter(fn ($subscription) => $subscription->status === SubscriptionStatus::Trial && $subscription->created_at?->format('Y-m') === $month->format('Y-m'))->count(),
                'churn' => $subscriptions->filter(fn ($subscription) => $subscription->cancelled_at?->format('Y-m') === $month->format('Y-m'))->count(),
            ];
        });

        return Inertia::render('Admin/Growth/Index', [
            'series' => $months,
            'stats' => [
                'accounts' => CustomerAccount::query()->count(),
                'newAccounts30d' => CustomerAccount::query()->where('created_at', '>=', now()->subDays(30))->count(),
                'activeTrials' => Subscription::query()->where('status', SubscriptionStatus::Trial->value)->count(),
                'churn30d' => Subscription::query()->whereNotNull('cancelled_at')->where('cancelled_at', '>=', now()->subDays(30))->count(),
                'conversion30d' => $this->conversionRate(),
            ],
            'recentAccounts' => CustomerAccount::query()->withCount('tenants')->latest()->limit(10)->get(['id', 'name', 'status', 'created_at']),
            'recentChurn' => Subscription::query()->with(['customerAccount:id,name', 'tenant:id,company_name', 'plan:id,name'])
                ->whereNotNull('cancelled_at')->latest('cancelled_at')->limit(10)->get(),
        ]);
    }

    private function conversionRate(): float
    {
        $started = Subscription::query()->where('created_at', '>=', now()->subDays(30))->count();
        if ($started === 0) {
            return 0;
        }

        $converted = Subscription::query()->where('created_at', '>=', now()->subDays(30))
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])->count();

        return round(($converted / $started) * 100, 1);
    }
}
