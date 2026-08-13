<?php

namespace App\Services\Platform;

use App\Enums\PortalUserStatus;
use App\Models\BillingProfile;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountActivity;
use App\Models\PortalUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerAccountService
{
    public function createWithOwner(PortalUser $owner, array $data): CustomerAccount
    {
        return DB::transaction(function () use ($owner, $data): CustomerAccount {
            if ($owner->status === PortalUserStatus::Invited) {
                $owner->forceFill(['status' => PortalUserStatus::Active])->save();
            }

            $account = CustomerAccount::create([
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? null,
                'account_number' => $this->newAccountNumber(),
                'status' => 'active',
                'type' => $data['type'] ?? 'business',
                'primary_owner_user_id' => $owner->getKey(),
                'billing_email' => $data['billing_email'] ?? $owner->email,
                'default_currency' => strtoupper($data['currency'] ?? 'USD'),
                'timezone' => $data['timezone'] ?? $owner->timezone ?? 'UTC',
                'locale' => $data['locale'] ?? $owner->locale ?? 'en',
                'billing_mode' => $data['billing_mode'] ?? 'per_service',
            ]);

            $account->users()->attach($owner->getKey(), [
                'role' => 'owner',
                'can_manage_services' => true,
                'can_manage_billing' => true,
                'can_manage_members' => true,
                'can_manage_support' => true,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            BillingProfile::create([
                'customer_account_id' => $account->getKey(),
                'billing_name' => $owner->name,
                'billing_email' => $account->billing_email,
                'company_name' => $account->legal_name ?: $account->name,
                'currency' => $account->default_currency,
            ]);

            CustomerAccountActivity::create([
                'customer_account_id' => $account->getKey(),
                'actor_type' => PortalUser::class,
                'actor_id' => (string) $owner->getKey(),
                'event' => 'account.created',
                'subject_type' => CustomerAccount::class,
                'subject_id' => (string) $account->getKey(),
                'description' => 'Customer account created.',
            ]);

            return $account;
        });
    }

    private function newAccountNumber(): string
    {
        do {
            $number = 'ACC-'.Str::upper(Str::random(10));
        } while (CustomerAccount::withTrashed()->where('account_number', $number)->exists());

        return $number;
    }
}
