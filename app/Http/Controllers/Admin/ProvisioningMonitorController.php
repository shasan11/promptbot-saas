<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProvisioningMonitorController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $query = Tenant::query()
            ->with(['customerAccount:id,name', 'plan:id,name', 'provisioningLogs' => fn ($logs) => $logs->latest()->limit(1)])
            ->when($request->string('search')->isNotEmpty(), function ($builder) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $builder->where(fn ($inner) => $inner->where('company_name', 'like', $search)
                    ->orWhere('slug', 'like', $search)
                    ->orWhereHas('customerAccount', fn ($account) => $account->where('name', 'like', $search)));
            });

        $this->applyStatus($query, $status);

        return Inertia::render('Admin/Operations/Provisioning', [
            'tenants' => $query->latest('updated_at')->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
            'stats' => [
                'pending' => Tenant::query()->whereNull('provisioned_at')->whereNull('last_provisioning_error')->where(fn ($q) => $q->whereNull('provisioning_step')->orWhere('provisioning_step', 'pending'))->count(),
                'running' => Tenant::query()->whereNull('provisioned_at')->whereNull('last_provisioning_error')->whereNotNull('provisioning_step')->where('provisioning_step', '!=', 'pending')->count(),
                'completed' => Tenant::query()->whereNotNull('provisioned_at')->whereNull('last_provisioning_error')->count(),
                'failed' => Tenant::query()->whereNotNull('last_provisioning_error')->count(),
            ],
        ]);
    }

    private function applyStatus($query, string $status): void
    {
        match ($status) {
            'pending' => $query->whereNull('provisioned_at')->whereNull('last_provisioning_error')->where(fn ($q) => $q->whereNull('provisioning_step')->orWhere('provisioning_step', 'pending')),
            'running' => $query->whereNull('provisioned_at')->whereNull('last_provisioning_error')->whereNotNull('provisioning_step')->where('provisioning_step', '!=', 'pending'),
            'completed' => $query->whereNotNull('provisioned_at')->whereNull('last_provisioning_error'),
            'failed' => $query->whereNotNull('last_provisioning_error'),
            default => null,
        };
    }
}
