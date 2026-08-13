<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class PortalController extends Controller
{
    protected function account(Request $request): CustomerAccount
    {
        return $request->attributes->get('customerAccount');
    }

    protected function membership(Request $request): object
    {
        return $this->account($request)->users()->where('portal_users.id', $request->user('portal')->getKey())->firstOrFail()->pivot;
    }

    protected function selectedWorkspaceAccess(Request $request): bool
    {
        $membership = $this->membership($request);
        return $membership->role !== 'owner' && $membership->service_access === 'selected';
    }

    protected function visibleTenantIds(Request $request): Collection
    {
        return $this->account($request)->tenantsVisibleTo($request->user('portal'))->pluck('tenants.id');
    }
}
