<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubscriptionUpdateRequest;
use App\Models\Subscription;
use App\Services\Platform\AuditLogService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => Subscription::query()->with(['tenant', 'plan'])->latest()->paginate(20),
        ]);
    }

    public function show(Subscription $subscription): Response
    {
        return Inertia::render('Admin/Subscriptions/Show', [
            'subscription' => $subscription->load(['tenant', 'plan']),
        ]);
    }

    public function update(SubscriptionUpdateRequest $request, Subscription $subscription, TenantProvisioningService $provisioning, AuditLogService $auditLog): RedirectResponse
    {
        $oldValues = $subscription->only(array_keys($request->validated()));
        $subscription->update($request->validated());
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
