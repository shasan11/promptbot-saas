<?php

namespace App\Services\Platform;

use App\Models\BillingProfile;
use App\Models\CustomerAccount;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class LegacyCustomerAccountResolver
{
    public function resolve(Tenant $tenant): CustomerAccount
    {
        if ($tenant->customer_account_id) return CustomerAccount::findOrFail($tenant->customer_account_id);

        return DB::transaction(function () use ($tenant): CustomerAccount {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            if ($tenant->customer_account_id) return CustomerAccount::findOrFail($tenant->customer_account_id);
            $number = 'ACC-LEGACY-'.strtoupper(substr(hash('sha256', (string) $tenant->getKey()), 0, 10));
            $account = CustomerAccount::firstOrCreate(['account_number' => $number], [
                'name' => $tenant->company_name, 'status' => 'active', 'type' => 'business',
                'default_currency' => 'USD', 'timezone' => 'UTC', 'locale' => 'en',
                'billing_mode' => 'per_service', 'metadata' => ['legacy_tenant_id' => $tenant->getKey()],
            ]);
            $tenant->forceFill(['customer_account_id' => $account->getKey()])->saveQuietly();
            BillingProfile::firstOrCreate(['customer_account_id' => $account->getKey()], ['company_name' => $tenant->company_name, 'currency' => 'USD']);
            return $account;
        });
    }
}
