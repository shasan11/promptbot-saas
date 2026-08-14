<?php

namespace App\Services\Platform;

use App\Models\CustomerAccount;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class LegacyCustomerAccountResolver
{
    public function __construct(private readonly DefaultCustomerAccountService $defaultAccount) {}

    public function resolve(Tenant $tenant): CustomerAccount
    {
        if ($tenant->customer_account_id) return CustomerAccount::findOrFail($tenant->customer_account_id);

        $account = $this->defaultAccount->get();

        $resolved = DB::transaction(function () use ($tenant, $account): CustomerAccount {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            if ($lockedTenant->customer_account_id) return CustomerAccount::findOrFail($lockedTenant->customer_account_id);
            $lockedTenant->forceFill(['customer_account_id' => $account->getKey()])->saveQuietly();
            return $account;
        });

        $tenant->forceFill(['customer_account_id' => $resolved->getKey()]);

        return $resolved;
    }
}
