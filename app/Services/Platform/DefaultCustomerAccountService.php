<?php

namespace App\Services\Platform;

use App\Models\BillingProfile;
use App\Models\CustomerAccount;
use Illuminate\Support\Facades\DB;

class DefaultCustomerAccountService
{
    public function get(): CustomerAccount
    {
        return DB::transaction(function (): CustomerAccount {
            $account = CustomerAccount::withTrashed()
                ->where('account_number', CustomerAccount::DEFAULT_ACCOUNT_NUMBER)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = new CustomerAccount([
                    'name' => 'Default Account',
                    'account_number' => CustomerAccount::DEFAULT_ACCOUNT_NUMBER,
                    'status' => 'active',
                    'type' => 'business',
                    'default_currency' => config('platform.default_currency', 'USD'),
                    'timezone' => config('app.timezone', 'UTC'),
                    'locale' => 'en',
                    'billing_mode' => 'per_service',
                    'metadata' => ['system_default' => true],
                ]);
                $account->save();
            } else {
                if ($account->trashed()) {
                    $account->restoreQuietly();
                }

                $metadata = $account->metadata ?? [];
                $account->forceFill([
                    'name' => $account->name ?: 'Default Account',
                    'status' => 'active',
                    'closed_at' => null,
                    'suspended_at' => null,
                    'metadata' => [...$metadata, 'system_default' => true],
                ])->saveQuietly();
            }

            BillingProfile::firstOrCreate(
                ['customer_account_id' => $account->getKey()],
                ['company_name' => 'Default Account', 'currency' => $account->default_currency ?: 'USD'],
            );

            return $account->refresh();
        });
    }
}
