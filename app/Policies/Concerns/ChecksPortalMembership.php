<?php

namespace App\Policies\Concerns;

use App\Models\CustomerAccount;
use App\Models\PortalUser;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

trait ChecksPortalMembership
{
    protected function membership(PortalUser $user, CustomerAccount $account): ?object
    {
        return $account->users()->where('portal_users.id', $user->getKey())->first()?->pivot;
    }

    protected function hasCapability(PortalUser $user, CustomerAccount $account, string $capability): bool
    {
        $membership = $this->membership($user, $account);
        if (! $membership) return false;

        return in_array($membership->role, ['owner', 'admin'], true) || (bool) $membership->{$capability};
    }

    protected function hasWorkspaceAccess(PortalUser $user, Tenant $tenant): bool
    {
        if (! $tenant->customer_account_id) return false;
        $membership = $this->membership($user, $tenant->customerAccount);
        if (! $membership) return false;
        if ($membership->role === 'owner' || $membership->service_access !== 'selected') return true;

        return DB::table('customer_account_user_tenants')
            ->where('customer_account_id', $tenant->customer_account_id)
            ->where('portal_user_id', $user->getKey())
            ->where('tenant_id', $tenant->getKey())
            ->exists();
    }
}
