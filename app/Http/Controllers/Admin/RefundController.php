<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentRefund;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RefundController extends Controller
{
    public function __invoke(Request $request, PlatformSettingsService $settings): Response
    {
        $currency = strtoupper((string) $settings->get('general', 'default_currency', 'USD'));
        $query = PaymentRefund::query()
            ->with([
                'payment:id,tenant_id,customer_account_id,invoice_id,provider,provider_reference,currency,amount,refunded_amount',
                'payment.tenant:id,company_name',
                'payment.customerAccount:id,name',
                'payment.invoice:id,number',
                'creator:id,name,email',
            ])
            ->when($request->string('search')->isNotEmpty(), function ($builder) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('provider_reference', 'like', $search)
                        ->orWhere('reason', 'like', $search)
                        ->orWhereHas('payment', fn ($payment) => $payment
                            ->where('provider_reference', 'like', $search)
                            ->orWhereHas('tenant', fn ($tenant) => $tenant->where('company_name', 'like', $search))
                            ->orWhereHas('customerAccount', fn ($account) => $account->where('name', 'like', $search)));
                });
            })
            ->when($request->string('status')->isNotEmpty(), fn ($builder) => $builder->where('status', $request->string('status')))
            ->when($request->string('from')->isNotEmpty(), fn ($builder) => $builder->whereDate('processed_at', '>=', $request->string('from')))
            ->when($request->string('to')->isNotEmpty(), fn ($builder) => $builder->whereDate('processed_at', '<=', $request->string('to')));

        return Inertia::render('Admin/Refunds/Index', [
            'refunds' => (clone $query)->latest('processed_at')->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'from', 'to']),
            'stats' => [
                'currency' => $currency,
                'total' => PaymentRefund::query()->count(),
                'completed' => PaymentRefund::query()->where('status', 'completed')->count(),
                'amount' => PaymentRefund::query()->where('status', 'completed')
                    ->whereHas('payment', fn ($payment) => $payment->where('currency', $currency))->sum('amount'),
                'thisMonth' => PaymentRefund::query()->where('status', 'completed')
                    ->where('processed_at', '>=', now()->startOfMonth())
                    ->whereHas('payment', fn ($payment) => $payment->where('currency', $currency))->sum('amount'),
            ],
        ]);
    }
}
