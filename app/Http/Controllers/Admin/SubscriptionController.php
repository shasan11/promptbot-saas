<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubscriptionUpdateRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\SubscriptionService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $subscriptions = Subscription::query()
            ->with(['tenant', 'plan'])
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('plan_id')->isNotEmpty(), fn ($query) => $query->where('plan_id', $request->string('plan_id')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'plans' => Plan::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'plan_id']),
        ]);
    }

    public function show(Subscription $subscription): Response
    {
        return Inertia::render('Admin/Subscriptions/Show', [
            'subscription' => $subscription->load(['tenant', 'plan']),
            'plans' => Plan::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(
        SubscriptionUpdateRequest $request,
        Subscription $subscription,
        TenantProvisioningService $provisioning,
        SubscriptionService $subscriptions,
        AuditLogService $auditLog
    ): RedirectResponse {
        $oldValues = $subscription->only(array_keys($request->validated()));
        $subscription->update($request->validated());

        if (array_key_exists('plan_id', $request->validated())) {
            $subscriptions->syncTenantPlan($subscription);
        }

        $auditLog->record('subscription.updated', $subscription, [
            'tenant_id' => $subscription->tenant_id,
            'old_values' => $oldValues,
            'new_values' => $request->validated(),
        ]);

        if (in_array($subscription->status?->value ?? $subscription->status, ['suspended', 'expired', 'cancelled'], true)) {
            $provisioning->suspend($subscription->tenant);
        } elseif (in_array($subscription->status?->value ?? $subscription->status, ['trial', 'active', 'manual'], true)) {
            $provisioning->activate($subscription->tenant);
        }

        return back()->with('status', 'Subscription updated.');
    }
}
